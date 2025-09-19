<?php
namespace App\Http\Controllers;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\TranscriptionLaravel;
use App\Models\PendingRecording;
use App\Models\Notification;
use App\Models\TaskLaravel;
use App\Traits\MeetingContentParsing;
use Illuminate\Support\Facades\Log;
use Google\Service\Drive as DriveService;
use Google\Service\Exception as GoogleServiceException;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Models\OrganizationActivity;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\UploadedFile;

class DriveController extends Controller
{
    use MeetingContentParsing;

    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
        Log::info('DriveController constructor called', ['user' => Auth::user() ? Auth::user()->username : null]);
    }

    /**
     * Ensure the four standard subfolders exist under the provided root folder:
     *  - Audios
     *  - Transcripciones
     *  - Audios Pospuestos
     *  - Documentos
     * Works for both personal and organization drives, creating missing folders in Google Drive
     * and persisting them in the corresponding DB tables. Returns an associative array with the
     * Subfolder / OrganizationSubfolder models: ['audio' => ..., 'transcription' => ..., 'pending' => ...]
     */
    private function ensureStandardSubfolders($rootFolder, bool $useOrgDrive, GoogleServiceAccount $serviceAccount): array
    {
        $names = [
            'audio'         => 'Audios',
            'transcription' => 'Transcripciones',
            'pending'       => 'Audios Pospuestos',
            'documents'     => 'Documentos',
        ];

        $result = [];
        $serviceEmail = config('services.google.service_account_email');
        $ownerEmail = $this->resolveRootOwnerEmail($rootFolder, $useOrgDrive);
        $impersonationActive = false;

        try {
            foreach ($names as $key => $folderName) {
                try {
                    if ($useOrgDrive) {
                        $model = OrganizationSubfolder::where('organization_folder_id', $rootFolder->id)
                            ->where('name', $folderName)
                            ->first();
                        if (!$model) {
                            // Try with the service account first (expected for shared drives)
                            try {
                                $googleId = $serviceAccount->createFolder($folderName, $rootFolder->google_id);
                            } catch (\Throwable $e) {
                                Log::warning('Org subfolder create with SA failed, trying impersonation', [
                                    'folder' => $folderName,
                                    'parent' => $rootFolder->google_id,
                                    'error'  => $e->getMessage(),
                                ]);
                                if ($ownerEmail) {
                                    try {
                                        $serviceAccount->impersonate($ownerEmail);
                                        $impersonationActive = true;
                                        $googleId = $serviceAccount->createFolder($folderName, $rootFolder->google_id);
                                    } catch (\Throwable $e2) {
                                        throw $e2; // bubble up to outer catch for logging
                                    }
                                } else {
                                    throw $e;
                                }
                            }
                            $model = OrganizationSubfolder::create([
                                'organization_folder_id' => $rootFolder->id,
                                'google_id'              => $googleId,
                                'name'                   => $folderName,
                            ]);
                            try { $serviceAccount->shareFolder($googleId, $serviceEmail); } catch (\Throwable $e) { /* ignore */ }
                        }
                    } else {
                        $model = Subfolder::where('folder_id', $rootFolder->id)
                            ->where('name', $folderName)
                            ->first();
                        if (!$model) {
                            $impersonatedForFolder = false;

                            try {
                                try {
                                    $googleId = $serviceAccount->createFolder($folderName, $rootFolder->google_id);
                                } catch (\Throwable $createException) {
                                    $requiresImpersonation = $this->shouldRetryWithImpersonation($createException);
                                    if ($requiresImpersonation && $ownerEmail) {
                                        Log::notice('Personal subfolder creation requires impersonation', [
                                            'folder'      => $folderName,
                                            'parent'      => $rootFolder->google_id,
                                            'ownerEmail'  => $ownerEmail,
                                            'error'       => $createException->getMessage(),
                                        ]);

                                        $serviceAccount->impersonate($ownerEmail);
                                        $impersonationActive = true;
                                        $impersonatedForFolder = true;
                                        $googleId = $serviceAccount->createFolder($folderName, $rootFolder->google_id);
                                    } elseif ($requiresImpersonation) {
                                        Log::error('Personal subfolder creation failed: impersonation unavailable', [
                                            'folder'     => $folderName,
                                            'parent'     => $rootFolder->google_id,
                                            'ownerEmail' => $ownerEmail,
                                            'error'      => $createException->getMessage(),
                                        ]);

                                        throw $createException;
                                    } else {
                                        throw $createException;
                                    }
                                }

                                $model = Subfolder::create([
                                    'folder_id' => $rootFolder->id,
                                    'google_id' => $googleId,
                                    'name'      => $folderName,
                                ]);
                                try { $serviceAccount->shareFolder($googleId, $serviceEmail); } catch (\Throwable $e) { /* ignore */ }
                            } finally {
                                if ($impersonatedForFolder) {
                                    $this->resetImpersonation($serviceAccount, $impersonationActive);
                                }
                            }
                        }
                    }
                    $result[$key] = $model;
                } catch (\Throwable $e) {
                    Log::warning('ensureStandardSubfolders failure', [
                        'folder' => $folderName,
                        'requires_impersonation' => $this->shouldRetryWithImpersonation($e),
                        'impersonation_active' => $impersonationActive,
                        'ownerEmailAvailable' => (bool) $ownerEmail,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            if ($impersonationActive) {
                $this->resetImpersonation($serviceAccount, $impersonationActive);
            }
        }

        if (!$ownerEmail) {
            Log::debug('ensureStandardSubfolders owner email not found', [
                'useOrgDrive' => $useOrgDrive,
                'rootFolderId' => $rootFolder->id ?? null,
            ]);
        }

        return $result;
    }

    private function shouldRetryWithImpersonation(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage() ?? '');
        $keywords = ['permission', 'insufficient', 'forbidden', 'invalid_grant', 'access denied'];

        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        if ($exception instanceof GoogleServiceException && method_exists($exception, 'getErrors')) {
            $errors = $exception->getErrors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $reason = strtolower($error['reason'] ?? '');
                    if (in_array($reason, ['invalid_grant', 'insufficientpermissions', 'forbidden', 'accessdenied'], true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function resetImpersonation(GoogleServiceAccount $serviceAccount, bool &$impersonationActive): void
    {
        if (! $impersonationActive) {
            return;
        }

        try {
            $serviceAccount->impersonate(null);
        } catch (\Throwable $e) {
            Log::debug('Failed to reset impersonation after ensuring subfolders', [
                'error' => $e->getMessage(),
            ]);
        }

        $impersonationActive = false;
    }

    private function resolveRootOwnerEmail($rootFolder, bool $useOrgDrive): ?string
    {
        if ($useOrgDrive && $rootFolder instanceof OrganizationFolder) {
            $rootFolder->loadMissing(['organization.admin', 'googleToken']);
            $token = $rootFolder->googleToken;
            if ($token) {
                $email = $token->impersonate_email
                    ?? $token->connected_email
                    ?? $token->owner_email
                    ?? $token->email
                    ?? null;
                if ($email) {
                    return $email;
                }
            }

            return optional(optional($rootFolder->organization)->admin)->email;
        }

        if (! $useOrgDrive && $rootFolder instanceof Folder) {
            $token = GoogleToken::find($rootFolder->google_token_id);
            if ($token) {
                return User::where('username', $token->username)->value('email');
            }
        }

        return null;
    }

    public function createMainFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'parentId' => 'nullable|string',
        ]);
        $token  = GoogleToken::where('username', Auth::user()->username)->firstOrFail();
        $client = $this->drive->getClient();

        $accessTokenString = $token->getAccessTokenString();
        if (!$accessTokenString) {
            return back()->withErrors(['token' => 'Token de Google inválido']);
        }

        $client->setAccessToken([
            'access_token'  => $accessTokenString,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);
        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (! isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            }
        }

        // Choose parent container where the root folder will be created (dynamic first, then config fallback)
        $parentRootId = (string) ($request->input('parentId') ?: config('drive.root_folder_id'));
        $ownerEmail = Auth::user()->email;
        $implicitUserDrive = false; // creating under user's My Drive without explicit parent
        if (empty($parentRootId)) {
            // Backward-compatible behavior: create at user's My Drive root via Service Account impersonation
            Log::info('createMainFolder: no parent provided; will create at user My Drive root via impersonation');
        } else {
            Log::info('createMainFolder: using parent', ['parentId' => $parentRootId]);
        }

        // Create folder using Service Account for homogeneity (with impersonation fallback)
        $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
        $folderId = null;
        $serviceAccountError = null;
        $impersonated = false;
        if (!$ownerEmail) {
            $ownerEmail = \App\Models\User::where('username', Auth::user()->username)->value('email');
        }

        $sharedWithSA = false;
        try {
            if (!empty($parentRootId)) {
                $folderId = $serviceAccount->createFolder($request->input('name'), $parentRootId);
            } else {
                // Create directly in user's My Drive using impersonation
                if ($ownerEmail) {
                    $serviceAccount->impersonate($ownerEmail);
                    $impersonated = true;
                    $implicitUserDrive = true;
                    $folderId = $serviceAccount->createFolder($request->input('name'));
                    // While impersonating the user (owner), grant SA access now
                    $serviceEmail = config('services.google.service_account_email');
                    if ($serviceEmail) {
                        try { $serviceAccount->shareFolder($folderId, $serviceEmail); $sharedWithSA = true; } catch (\Throwable $e) { /* ignore */ }
                    }
                } else {
                    throw new RuntimeException('No se pudo determinar el email del propietario para crear la carpeta en su Drive');
                }
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (GoogleServiceException $e) {
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                Log::warning('createMainFolder invalid_grant', [
                    'username' => Auth::user()->username,
                ]);
                $token->update([
                    'access_token'  => null,
                    'refresh_token' => null,
                    'expiry_date'   => null,
                    // recordings_folder_id se mantiene para reconexión
                ]);
                return response()->json([
                    'message' => 'Token de Google inválido, vuelve a conectar Google Drive.',
                ], 401);
            }

            $serviceAccountError = $e;
            if ($ownerEmail) {
                try {
                    $serviceAccount->impersonate($ownerEmail);
                    $impersonated = true;
                    $folderId = $serviceAccount->createFolder($request->input('name'), $parentRootId);
                } catch (\Throwable $impersonationError) {
                    $serviceAccountError = $impersonationError;
                }
            }
        } catch (\Throwable $e) {
            $serviceAccountError = $e;
            if ($ownerEmail) {
                try {
                    $serviceAccount->impersonate($ownerEmail);
                    $impersonated = true;
                    $folderId = $serviceAccount->createFolder($request->input('name'), $parentRootId);
                } catch (\Throwable $impersonationError) {
                    $serviceAccountError = $impersonationError;
                }
            }
        } finally {
            if ($impersonated) {
                try { $serviceAccount->impersonate(null); } catch (\Throwable $e) { /* ignore */ }
            }
        }

        $usedOAuthFallback = false;
        if (!$folderId) {
            Log::warning('createMainFolder falling back to OAuth client', [
                'username' => Auth::user()->username,
                'error' => $serviceAccountError?->getMessage(),
            ]);

            try {
                $folderId = $this->drive->createFolder($request->input('name'), $parentRootId);
                $usedOAuthFallback = true;
            } catch (\Throwable $oauthException) {
                Log::error('createMainFolder Google error', [
                    'username' => Auth::user()->username,
                    'error'    => $oauthException->getMessage(),
                    'service_error' => $serviceAccountError?->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Error de Drive: ' . $oauthException->getMessage(),
                ], 502);
            }
        }

        GoogleToken::where('username', Auth::user()->username)
            ->update(['recordings_folder_id' => $folderId]);

        $folder = Folder::create([
            'google_token_id' => $token->id,
            'google_id'       => $folderId,
            'name'            => $request->input('name'),
            'parent_id'       => null,
        ]);

        $serviceEmail = config('services.google.service_account_email');
        // Share root with Service Account (if created via OAuth) and with the user (always)
        try {
            if ($usedOAuthFallback && $serviceEmail) {
                // Folder created via OAuth: ensure SA has access
                $this->drive->shareFolder($folderId, $serviceEmail);
            } elseif ($implicitUserDrive && $serviceEmail && !$sharedWithSA) {
                // Fallback attempt (should already be shared during impersonation)
                try { $serviceAccount->shareFolder($folderId, $serviceEmail); } catch (\Throwable $e) { /* ignore */ }
            }
        } catch (\Throwable $shareError) {
            Log::warning('createMainFolder failed to share root with SA via OAuth', [
                'username' => Auth::user()->username,
                'error' => $shareError->getMessage(),
            ]);
        }
        try {
            $userEmail = Auth::user()->email;
            if ($userEmail && !$implicitUserDrive) {
                // Ensure the OAuth user can access a folder created by the SA (non-impersonated)
                $serviceAccount->shareItem($folderId, $userEmail, 'writer');
            }
        } catch (\Throwable $shareUserError) {
            Log::warning('createMainFolder failed to share root with user', [
                'username' => Auth::user()->username,
                'error' => $shareUserError->getMessage(),
            ]);
        }

        // Ensure standard subfolders exist in the new personal root (idempotent) using SA
        try {
            $serviceEmail = config('services.google.service_account_email');
            $needed = ['Audios', 'Transcripciones', 'Audios Pospuestos', 'Documentos'];
            foreach ($needed as $name) {
                try {
                    $subId = $serviceAccount->createFolder($name, $folderId);
                    \App\Models\Subfolder::firstOrCreate([
                        'folder_id' => $folder->id,
                        'google_id' => $subId,
                    ], ['name' => $name]);
                    try { if ($serviceEmail) { $serviceAccount->shareFolder($subId, $serviceEmail); } } catch (\Throwable $e) { /* ignore */ }
                    // Also share with the user so OAuth can read
                    try {
                        $userEmail = Auth::user()->email;
                        if ($userEmail) { $serviceAccount->shareItem($subId, $userEmail, 'writer'); }
                    } catch (\Throwable $e) { /* ignore */ }
                } catch (\Throwable $e) {
                    Log::warning('createMainFolder: failed to create standard subfolder', [
                        'name' => $name,
                        'parent' => $folderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('createMainFolder: ensure standard subfolders failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['id' => $folderId]);
    }

    public function setMainFolder(Request $request)
    {
        $token = GoogleToken::where('username', Auth::user()->username)->first();
        if (! $token) {
            return response()->json([], 404);
        }

        $client = $this->drive->getClient();
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);
        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (! isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            }
        }

        $drive      = $this->drive->getDrive();
        $folderData = $drive->files->get($request->input('id'), [
            'fields' => 'name',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);
        $folderName = $folderData->getName();

        $token->recordings_folder_id = $request->input('id');
        $token->save();

        $folder = Folder::updateOrCreate(
            [
                'google_token_id' => $token->id,
                'google_id'       => $request->input('id'),
            ],
            [
                'name'      => $folderName,
                'parent_id' => null,
            ]
        );

        $files = $this->drive->listSubfolders($request->input('id'));
        $subfolders = [];
        foreach ($files as $file) {
            $subfolders[] = Subfolder::updateOrCreate(
                ['folder_id' => $folder->id, 'google_id' => $file->getId()],
                ['name' => $file->getName()]
            );
        }

        try {
            $this->drive->shareFolder(
                $request->input('id'),
                config('services.google.service_account_email')
            );
        } catch (GoogleServiceException|Throwable $e) {
            Log::warning('setMainFolder shareFolder failed', [
                'id'    => $request->input('id'),
                'error' => $e->getMessage(),
            ]);
        }

        // Ensure standard subfolders exist under the selected root folder (idempotent)
        try {
            $serviceEmail = config('services.google.service_account_email');
            // Build a set of existing subfolder names (case-insensitive)
            $existing = [];
            foreach ($subfolders as $sf) {
                $existing[strtolower($sf->name)] = true;
            }
            $needed = ['Audios', 'Transcripciones', 'Audios Pospuestos', 'Documentos'];
            foreach ($needed as $name) {
                if (!isset($existing[strtolower($name)])) {
                    try {
                        $newId = $this->drive->createFolder($name, $request->input('id'));
                        $model = Subfolder::create([
                            'folder_id' => $folder->id,
                            'google_id' => $newId,
                            'name'      => $name,
                        ]);
                        // Share subfolder with service account for later automation
                        try { $this->drive->shareFolder($newId, $serviceEmail); } catch (\Throwable $e) { /* ignore */ }
                        $subfolders[] = $model;
                    } catch (\Throwable $e) {
                        Log::warning('setMainFolder: failed to create standard subfolder', [
                            'name' => $name,
                            'parent' => $request->input('id'),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('setMainFolder: ensure standard subfolders failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'id'         => $request->input('id'),
            'name'       => $folderName,
            'subfolders' => $subfolders,
        ]);
    }

    public function createSubfolder(Request $request)
    {
        return response()->json([
            'deprecated' => true,
            'message' => 'La creación manual de subcarpetas fue deshabilitada. Ahora se crean automáticamente (Audios, Transcripciones, Audios Pospuestos, Documentos).'
        ], 410);
    }

    public function syncDriveSubfolders(Request $request)
    {
        // Compatibilidad: algunos frontends antiguos aún llaman a este endpoint.
        // Devolvemos la carpeta raíz y un array vacío de subcarpetas para evitar errores 410.
        $username = Auth::user()->username;
        $token = GoogleToken::where('username', $username)->first();
        if (!$token) {
            return response()->json([
                'root_folder' => null,
                'subfolders' => [],
                'message' => 'Token no encontrado',
                'deprecated' => true,
            ], 200);
        }
        $rootFolder = Folder::where('google_token_id', $token->id)
            ->whereNull('parent_id')
            ->first();
        return response()->json([
            'root_folder' => $rootFolder,
            'subfolders' => [],
            'deprecated' => true,
            'message' => 'Estructura automática activa. No se requieren subcarpetas manuales.'
        ], 200);
    }
    public function status()
    {
        $connected = GoogleToken::where('username', Auth::user()->username)->exists();

        return response()->json(['connected' => $connected]);
    }

    public function deleteSubfolder(string $id)
    {
        return response()->json([
            'deprecated' => true,
            'message' => 'El borrado manual de subcarpetas ha sido deshabilitado.'
        ], 410);
    }

    public function uploadPendingAudio(Request $request)
    {
        Log::info('uploadPendingAudio entry', [
            'user' => Auth::user() ? Auth::user()->username : null,
            'request_all' => $request->all(),
            'has_file' => $request->hasFile('audioFile'),
            'file_info' => $request->file('audioFile') ? [
                'originalName' => $request->file('audioFile')->getClientOriginalName(),
                'mimeType' => $request->file('audioFile')->getMimeType(),
                'size' => $request->file('audioFile')->getSize(),
            ] : null,
        ]);
        set_time_limit(300);
    $maxAudioBytes = 200 * 1024 * 1024; // 200 MB
        try {
            $v = $request->validate([
                'meetingName' => 'required|string',
                'audioFile'   => 'required|file|mimetypes:audio/mpeg,audio/mp3,audio/webm,video/webm,audio/ogg,audio/wav,audio/x-wav,audio/wave,audio/mp4,video/mp4',
                'rootFolder'  => 'nullable|string', // Cambiar a nullable
                'driveType'   => 'nullable|string|in:personal,organization', // Nuevo campo para tipo de drive
            ]);

            $user = Auth::user();
            // Gate: postpone-only feature availability by plan
            // This endpoint is exclusively used for "posponer" uploads. For free/basic it's disabled.
            try {
                $planService = app(\App\Services\PlanLimitService::class);
                $limits = $planService->getLimitsForUser($user);
                if (!$limits['allow_postpone']) {
                    return response()->json([
                        'code' => 'POSTPONE_NOT_ALLOWED',
                        'message' => 'La opción de posponer está disponible para los planes Negocios y Enterprise.',
                        'allowed_plans' => ['negocios','enterprise']
                    ], 403);
                }
            } catch (\Throwable $e) {
                Log::warning('uploadPendingAudio: plan limits check failed', ['error' => $e->getMessage()]);
            }
            $serviceAccount = app(GoogleServiceAccount::class);

            $organizationFolder = $user->organizationFolder;
            // Simplificación: permitir uso del Drive organizacional si existe la carpeta raíz
            // Nota: anteriormente se comprobaba un rol 'colaborador/administrador' que nunca se establecía aquí,
            // lo que impedía el uso del Drive de la organización incluso siendo miembro. Corregido.

            // Determinar si usar Drive organizacional basado en driveType
            $driveType = $v['driveType'] ?? 'personal'; // Default a personal si no se especifica
            $useOrgDrive = false;

            Log::info('uploadPendingAudio: Drive type selection', [
                'driveType' => $driveType,
                'hasOrganizationFolder' => !!$organizationFolder,
                'username' => $user->username
            ]);

            if ($driveType === 'organization' && $organizationFolder) {
                $useOrgDrive = true;
                Log::info('uploadPendingAudio: Using organization drive', [
                    'orgFolderId' => $organizationFolder->google_id
                ]);
            } elseif ($driveType === 'organization' && !$organizationFolder) {
                Log::warning('uploadPendingAudio: Organization drive requested but no organization folder found', [
                    'username' => $user->username
                ]);
                return response()->json([
                    'message' => 'No tienes acceso a Drive organizacional o no está configurado'
                ], 403);
            }

            if ($useOrgDrive) {
                // Usar carpeta de organización
                $rootFolder = $organizationFolder;
                $rootFolderId = $organizationFolder->google_id;

                Log::info('uploadPendingAudio: Using organization folder', [
                    'orgFolderId' => $rootFolderId,
                    'orgFolderName' => $organizationFolder->name ?? 'Unknown'
                ]);
            } else {
                // Usar carpeta personal
                $token = GoogleToken::where('username', $user->username)->first();
                if (! $token) {
                    Log::error('uploadPendingAudio: google token not found', [
                        'username' => $user->username,
                    ]);
                    return response()->json(['message' => 'Token de Google no encontrado'], 400);
                }

                // Si no se especifica rootFolder, usar la primera carpeta raíz del usuario
                if (empty($v['rootFolder'])) {
                    $rootFolder = Folder::where('google_token_id', $token->id)
                        ->whereNull('parent_id')
                        ->first();
                } else {
                    $rootFolder = Folder::where('google_token_id', $token->id)
                        ->whereNull('parent_id')
                        ->where(function ($q) use ($v) {
                            $q->where('google_id', $v['rootFolder'])
                              ->orWhere('id', $v['rootFolder']);
                        })
                        ->first();
                }

                if (! $rootFolder) {
                    // Try to create a default root folder if none exists
                    Log::info('uploadPendingAudio: creating default root folder for user', [
                        'username' => $user->username,
                    ]);

                    try {
                        // Create a default "Grabaciones" folder in user's Drive using existing service
                        $defaultFolderName = 'Grabaciones';

                        // Use the existing drive service to create folder
                        $driveService = app(\App\Services\GoogleDriveService::class);
                        $driveService->setToken($token);

                        $parentRootFolderId = config('drive.root_folder_id');
                        if (empty($parentRootFolderId)) {
                            Log::error('uploadPendingAudio: missing configured root folder id for default creation', [
                                'username' => $user->username,
                            ]);

                            return response()->json(['message' => 'Carpeta raíz de Drive no configurada'], 500);
                        }

                        $folderId = $driveService->createFolder($defaultFolderName, $parentRootFolderId);

                        // Save the folder to database
                        $rootFolder = Folder::create([
                            'name' => $defaultFolderName,
                            'google_id' => $folderId,
                            'google_token_id' => $token->id,
                            'parent_id' => null,
                        ]);

                        Log::info('uploadPendingAudio: default root folder created', [
                            'username' => $user->username,
                            'folder_id' => $folderId,
                            'folder_name' => $defaultFolderName,
                            'parent_root_id' => $parentRootFolderId,
                        ]);

                    } catch (\Exception $e) {
                        Log::error('uploadPendingAudio: failed to create default root folder', [
                            'username' => $user->username,
                            'error' => $e->getMessage(),
                        ]);
                        return response()->json(['message' => 'No se pudo crear carpeta por defecto: ' . $e->getMessage()], 500);
                    }
                }

                if (! $rootFolder) {
                    Log::error('uploadPendingAudio: root folder still not found after creation attempt', [
                        'username' => $user->username,
                        'rootFolder' => $v['rootFolder'] ?? 'not specified',
                    ]);
                    return response()->json(['message' => 'Carpeta raíz no encontrada'], 400);
                }
                $rootFolderId = $rootFolder->google_id;
            }

            $serviceEmail = config('services.google.service_account_email');
            try {
                app(\App\Services\GoogleServiceAccount::class)->shareFolder($rootFolderId, $serviceEmail);
            } catch (GoogleServiceException $e) {
                if (
                    $e->getCode() === 404 ||
                    $e->getCode() === 403 ||
                    str_contains($e->getMessage(), 'File not found') ||
                    str_contains($e->getMessage(), 'The caller does not have permission')
                ) {
                    return response()->json([
                        'code'    => 'FOLDER_NOT_SHARED',
                        'message' => "La carpeta principal no está compartida con la cuenta de servicio. Comparte la carpeta con {$serviceEmail}",
                    ], 403);
                }
            }

            // Usar helper para garantizar subcarpetas estándar
            $standard = $this->ensureStandardSubfolders($rootFolder, $useOrgDrive, $serviceAccount);
            $subfolder = $standard['pending'] ?? null; // Audios Pospuestos
            if (!$subfolder) {
                return response()->json(['message' => 'No se pudo preparar la subcarpeta de audios pospuestos'], 500);
            }
            $pendingFolderId = $subfolder->google_id;
            $pendingSubfolderName = 'Audios Pospuestos';
            $subfolderCreated = false; // creación ya manejada dentro del helper

            // Nota: Si la cuenta de servicio no tiene acceso de escritura al folder raíz,
            // la creación/subida fallará con 403/404. Pediremos que compartan con la SA.

            // 3. Subir el audio a la subcarpeta
            $file = $request->file('audioFile');
            if ($file->getSize() > $maxAudioBytes) {
                return response()->json(['message' => 'Archivo de audio demasiado grande (máx. 200 MB)'], 413);
            }
            $filePath = $file->getRealPath();
            $mime = $file->getMimeType();
            $mimeToExt = [
                'audio/mpeg' => 'mp3',
                'audio/mp3'  => 'mp3',
                'audio/webm' => 'webm',
                'video/webm' => 'webm',
                'audio/ogg'  => 'ogg',
                'audio/wav'  => 'wav',
                'audio/x-wav' => 'wav',
                'audio/wave' => 'wav',
                'audio/mp4'  => 'mp4',
            ];
            $baseMime = explode(';', $mime)[0];
            $ext = $mimeToExt[$baseMime] ?? preg_replace('/[^\w]/', '', explode('/', $baseMime, 2)[1] ?? '');

            // Conversión a OGG si está activada (política: subir solo OGG)
            $converted = null;
            if (config('audio.force_ogg')) {
                try {
                    $conversionService = app(\App\Services\AudioConversionService::class);
                    $converted = $conversionService->convertToOgg($filePath, $mime, $ext);
                    if ($converted['was_converted']) {
                        $filePath = $converted['path'];
                        $mime = $converted['mime_type'];
                        $ext = 'ogg';
                        Log::info('uploadPendingAudio: audio converted to ogg', [
                            'meeting' => $v['meetingName'],
                            'mime' => $mime,
                        ]);
                    } else {
                        // Si no convirtió y no era ya OGG, forzamos error si la política exige OGG
                        $alreadyOgg = str_contains(strtolower($mime), 'ogg') || str_ends_with(strtolower($filePath), '.ogg');
                        if (!$alreadyOgg) {
                            return response()->json([
                                'code' => 'OGG_REQUIRED',
                                'message' => 'La conversión a OGG es obligatoria y no se pudo completar.',
                            ], 500);
                        }
                    }
                } catch (\App\Exceptions\FfmpegUnavailableException $e) {
                    Log::warning('uploadPendingAudio: ffmpeg unavailable - OGG required policy', ['error' => $e->getMessage()]);
                    return response()->json([
                        'code' => 'FFMPEG_UNAVAILABLE',
                        'message' => 'FFmpeg no está disponible en el servidor. La conversión a OGG es obligatoria. Instala ffmpeg o desactiva AUDIO_FORCE_OGG para desarrollo.',
                    ], 500);
                } catch (\Throwable $e) {
                    Log::error('uploadPendingAudio: ogg conversion failed (policy requires OGG)', ['error' => $e->getMessage()]);
                    return response()->json([
                        'code' => 'OGG_CONVERSION_FAILED',
                        'message' => 'Falló la conversión a OGG. Intenta con otro archivo o contacta al administrador.',
                    ], 500);
                }
            }

            $fileName = $v['meetingName'] . '.' . $ext;

            Log::debug('uploadPendingAudio uploading to Drive', [
                'fileName' => $fileName,
                'mime' => $mime,
                'pendingFolderId' => $pendingFolderId,
                'filePath' => $filePath,
                'converted' => $converted ? ($converted['was_converted'] ? 'yes' : 'no') : 'disabled',
            ]);
            $fileContents = file_get_contents($filePath);
            $fileId = $serviceAccount->uploadFile(
                $fileName,
                $mime,
                $pendingFolderId,
                $fileContents
            );
            if ($converted && $converted['was_converted']) {
                @unlink($converted['path']);
            }
            $audioUrl = $serviceAccount->getFileLink($fileId);

            // 4. Guardar en la BD
            Log::debug('uploadPendingAudio saving PendingRecording', [
                'username' => $user->username,
                'meeting_name' => $fileName,
                'audio_drive_id' => $fileId,
                'audio_download_url' => $audioUrl,
            ]);
            $pending = PendingRecording::create([
                'username'           => $user->username,
                'meeting_name'       => $fileName,
                'audio_drive_id'     => $fileId,
                'audio_download_url' => $audioUrl,
                'status'             => PendingRecording::STATUS_PENDING,
                'error_message'      => null,
            ]);

            Notification::create([
                'remitente' => $user->id,
                'emisor'    => $user->id,
                'status'    => 'pending',
                'message'   => 'Subida iniciada',
                'type'      => 'audio_upload',
                'data'      => [
                    'pending_recording_id' => $pending->id,
                    'meeting_name'         => $fileName,
                ],
            ]);

            $response = [
                'saved'              => true,
                'audio_drive_id'     => $fileId,
                'audio_download_url' => $audioUrl,
                'pending_recording'  => $pending->id,
                'status'             => $pending->status,
                'audio_name'         => $fileName,
                'subfolder_id'       => $subfolder->id,
                'subfolder_created'  => $subfolderCreated,
                'drive_type'         => $useOrgDrive ? 'organization' : 'personal', // Información del tipo de drive usado
                'folder_info'        => [
                    'root_folder' => $rootFolder->name ?? 'Grabaciones',
                    'subfolder'   => $pendingSubfolderName,
                    'full_path'   => ($rootFolder->name ?? 'Grabaciones') . '/' . $pendingSubfolderName,
                    'drive_type'  => $useOrgDrive ? 'organization' : 'personal'
                ],
            ];
            // Registrar carpeta pendiente en tabla auxiliar (idempotente)
            \App\Models\PendingFolder::firstOrCreate([
                'username' => $user->username,
                'google_id' => $subfolder->google_id,
            ], [
                'name' => $subfolder->name,
            ]);
            return response()->json($response);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['audioFile'][0] ?? 'Datos inválidos',
                'errors'  => $e->errors(),
            ], 422);
        } catch (GoogleServiceException $e) {
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                Log::warning('uploadPendingAudio invalid_grant', [
                    'username' => $user->username,
                    'error'    => $e->getMessage(),
                ]);
                $token?->delete();
                return response()->json([
                    'message' => 'Autenticación de Google expirada. Reconecta tu Google Drive.',
                ], 401);
            }

            if (
                $e->getCode() === 404 ||
                $e->getCode() === 403 ||
                str_contains($e->getMessage(), 'File not found') ||
                str_contains($e->getMessage(), 'The caller does not have permission')
            ) {
                return response()->json([
                    'code'    => 'FOLDER_NOT_SHARED',
                    'message' => 'La carpeta raíz no está compartida con la cuenta de servicio. Comparte la carpeta con ' . config('services.google.service_account_email'),
                ], 403);
            }

            Log::error('uploadPendingAudio Google error', [
                'username' => $user->username,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error de Drive: ' . $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error('uploadPendingAudio failed', [
                'error' => $e->getMessage(),
                'username' => Auth::user()?->username,
            ]);
            return response()->json([
                'message' => 'Error uploading audio file',
            ], 500);
        }
    }

    /**
     * Upload an audio file to the user's pending folder in Google Drive.
     *
     * @deprecated Use uploadPendingAudio instead.
     */
    public function uploadPendingRecording(Request $request)
    {
        $v = $request->validate([
            'meetingName'   => 'required|string',
            'audioData'     => 'required|string',
            'audioMimeType' => 'required|string',
        ]);

        // Resolve the service account and obtain (or create) the pending folder
        $serviceAccount = app(GoogleServiceAccount::class);
        $pendingFolder  = $serviceAccount->getOrCreatePendingFolder(Auth::user());
        $pendingFolderId = $pendingFolder['id'];

        // Decode the base64 audio payload
        $b64 = $v['audioData'];
        if (str_contains($b64, ',')) {
            [, $b64] = explode(',', $b64, 2);
        }
        $raw = base64_decode($b64);

        $tmp = tempnam(sys_get_temp_dir(), 'aud');
        file_put_contents($tmp, $raw);

        // Determine a suitable extension from the mime type
        $mime = strtolower($v['audioMimeType']);
        $baseMime = explode(';', $mime)[0];
        $mimeToExt = [
            'audio/mpeg'       => 'mp3',
            'audio/mp3'        => 'mp3',
            'audio/aac'        => 'aac',
            'audio/x-aac'      => 'aac',
            'audio/mp4'        => 'm4a',
            'audio/x-m4a'      => 'm4a',
            'audio/m4a'        => 'm4a',
            'audio/webm'       => 'webm',
            'audio/ogg'        => 'ogg',
            'application/ogg'  => 'ogg',
            'audio/x-opus+ogg' => 'ogg',
            'audio/opus'       => 'opus',
            'video/webm'       => 'webm',
            'video/mp4'       => 'mp4',
            'audio/wav'        => 'wav',
            'audio/x-wav'      => 'wav',
            'audio/wave'       => 'wav',
            'audio/flac'       => 'flac',
            'audio/x-flac'     => 'flac',
            'audio/amr'        => 'amr',
            'audio/3gpp'       => '3gp',
            'audio/3gpp2'      => '3g2',
        ];
        $ext = $mimeToExt[$baseMime] ?? null;
        if (empty($ext)) {
            $ext = 'ogg';
        }

        // Upload the audio file to Drive using the pending folder as parent
        $fileId = $serviceAccount->uploadFile(
            $v['meetingName'] . '.' . $ext,
            $v['audioMimeType'],
            $pendingFolderId,
            $tmp
        );

        @unlink($tmp);

        return response()->json(['id' => $fileId]);
    }
    public function saveResults(Request $request)
    {
        Log::info('saveResults reached', ['user' => Auth::user() ? Auth::user()->username : null]);
        Log::debug('saveResults before validation', [
            'meetingName' => $request->input('meetingName'),
            'audioLength' => strlen($request->input('audioData', '')),
        ]);
        // Permitir hasta 5 minutos de ejecución para cargas grandes
        set_time_limit(300);
        // 1. Validación: ahora esperamos también el mime type del audio
        $maxAudioBytes = 200 * 1024 * 1024; // 200 MB

        $mimeToExt = [
            'audio/mpeg'       => 'mp3',
            'audio/mp3'        => 'mp3',
            'audio/aac'        => 'aac',
            'audio/x-aac'      => 'aac',
            'audio/mp4'        => 'm4a',
            'audio/x-m4a'      => 'm4a',
            'audio/m4a'        => 'm4a',
            'audio/webm'       => 'webm',
            'video/webm'       => 'webm',
            'audio/ogg'        => 'ogg',
            'application/ogg'  => 'ogg',
            'audio/x-opus+ogg' => 'ogg',
            'audio/opus'       => 'opus',
            'audio/wav'        => 'wav',
            'audio/x-wav'      => 'wav',
            'audio/wave'       => 'wav',
            'audio/flac'       => 'flac',
            'audio/x-flac'     => 'flac',
            'audio/amr'        => 'amr',
            'audio/3gpp'       => '3gp',
            'audio/3gpp2'      => '3g2',
            'video/mp4'        => 'mp4',
            'video/3gpp'       => '3gp',
            'video/3gpp2'      => '3g2',
        ];

        $allowedAudioMimes = implode(',', array_keys($mimeToExt));

        $v = $request->validate([
            'meetingName'            => 'required|string',
            'rootFolder'             => 'nullable|string', // ahora opcional; se autodetecta
            'transcriptionSubfolder' => 'nullable|string',
            'audioSubfolder'         => 'nullable|string',
            'transcriptionData'      => 'required',
            'analysisResults'        => 'required',
            'audioData'              => 'required_without:audioFile|string|max:' . (int) ceil($maxAudioBytes * 4 / 3),      // Base64 (~266MB bruto)
            'audioMimeType'          => 'required_without:audioFile|string',      // p.ej. "audio/webm"
            'audioFile'              => 'required_without:audioData|file|mimetypes:' . $allowedAudioMimes . '|max:204800',
            'driveType'              => 'nullable|string|in:personal,organization', // Nuevo campo para tipo de drive
        ], [
            'audioData.max' => 'Archivo de audio demasiado grande (máx. 200 MB)',
        ]);

        if (is_string($v['transcriptionData'])) {
            $decoded = json_decode($v['transcriptionData'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $v['transcriptionData'] = $decoded;
            }
        }

        if (is_string($v['analysisResults'])) {
            $decoded = json_decode($v['analysisResults'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $v['analysisResults'] = $decoded;
            }
        }

        if (!is_array($v['transcriptionData'])) {
            return response()->json([
                'message' => 'Transcripción inválida',
            ], 422);
        }

        if (!is_array($v['analysisResults'])) {
            if (is_null($v['analysisResults'])) {
                $v['analysisResults'] = [];
            } else {
                return response()->json([
                    'message' => 'Resultados de análisis inválidos',
                ], 422);
            }
        }

        $user = Auth::user();
        // Enforce monthly meetings limit (count save as creating a meeting)
        try {
            $planService = app(\App\Services\PlanLimitService::class);
            if (!$planService->canCreateAnotherMeeting($user)) {
                $limits = $planService->getLimitsForUser($user);
                return response()->json([
                    'code' => 'PLAN_LIMIT_REACHED',
                    'message' => 'Has alcanzado el número máximo de reuniones para tu plan este mes.',
                    'used' => $limits['used_this_month'],
                    'max' => $limits['max_meetings_per_month']
                ], 403);
            }
        } catch (\Throwable $e) {
            Log::warning('saveResults: plan limit check failed', ['error' => $e->getMessage()]);
        }

        $user = Auth::user();
    $organizationFolder = $user->organizationFolder;

        // Determinar si usar Drive organizacional basado en driveType
        $driveType = $v['driveType'] ?? 'personal'; // Default a personal si no se especifica
        $useOrgDrive = false;

        Log::info('saveResults: Drive type selection', [
            'driveType' => $driveType,
            'hasOrganizationFolder' => !!$organizationFolder,
            'username' => $user->username,
            'rootFolder_param' => $v['rootFolder'] ?? null
        ]);

        if ($driveType === 'organization' && $organizationFolder) {
            $useOrgDrive = true;
                Log::info('saveResults: Using organization drive', [
                    'orgFolderId' => $organizationFolder->google_id
                ]);
        } elseif ($driveType === 'organization' && !$organizationFolder) {
            Log::warning('saveResults: Organization drive requested but no organization folder found', [
                'username' => $user->username
            ]);
            return response()->json([
                'message' => 'No tienes acceso a Drive organizacional o no está configurado'
            ], 403);
        }

        if ($useOrgDrive) {
            $rootFolder = $organizationFolder; // OrganizationFolder model
        } else {
            $token = GoogleToken::where('username', $user->username)->first();
            if (!$token) {
                return response()->json(['message' => 'Token de Google no encontrado'], 400);
            }

            if (!empty($v['rootFolder'])) {
                $rootFolder = Folder::where('google_token_id', $token->id)
                    ->where(function($q) use ($v) {
                        $q->where('google_id', $v['rootFolder'])
                          ->orWhere('id', $v['rootFolder']);
                    })
                    ->first();
            } else {
                // Autodetectar primera carpeta raíz del usuario
                $rootFolder = Folder::where('google_token_id', $token->id)
                    ->whereNull('parent_id')
                    ->first();
            }
            if (!$rootFolder) {
                return response()->json(['message' => 'Carpeta principal no encontrada o no configurada'], 400);
            }
        }

        // Asegurar que la cuenta de servicio tiene acceso de escritura al folder raíz antes de crear subcarpetas
        $serviceEmail = config('services.google.service_account_email');
        $serviceAccount = app(GoogleServiceAccount::class);
        try {
            $serviceAccount->shareFolder($rootFolder->google_id, $serviceEmail);
        } catch (GoogleServiceException $e) {
            if (
                $e->getCode() === 404 ||
                $e->getCode() === 403 ||
                str_contains($e->getMessage(), 'File not found') ||
                str_contains($e->getMessage(), 'The caller does not have permission')
            ) {
                return response()->json([
                    'code'    => 'FOLDER_NOT_SHARED',
                    'message' => "La carpeta principal no está compartida con la cuenta de servicio. Comparte la carpeta con {$serviceEmail}",
                ], 403);
            }
        }

        // Garantizar subcarpetas estándar dentro del folder raíz seleccionado
        $standard = $this->ensureStandardSubfolders($rootFolder, $useOrgDrive, $serviceAccount);
        $audioModel = $standard['audio'] ?? null;
        $transModel = $standard['transcription'] ?? null;
        if (!$audioModel || !$transModel) {
            return response()->json([
                'message' => 'No se pudieron preparar las subcarpetas estándar (Audios/Transcripciones) dentro de la carpeta raíz. Verifica permisos de la cuenta de servicio.'
            ], 500);
        }
        $audioFolderId = $audioModel->google_id;
        $transcriptionFolderId = $transModel->google_id;

        $accountEmail = $serviceEmail;

        try {
            $serviceAccount->shareFolder($transcriptionFolderId, $accountEmail);
        } catch (GoogleServiceException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'already')) {
                Log::error('saveResults share failure', [
                    'error'                 => $e->getMessage(),
                    'transcription_folder'  => $transcriptionFolderId,
                    'audio_folder'          => $audioFolderId,
                    'service_account_email' => $accountEmail,
                ]);

                return response()->json([
                    'message' => 'Error de Drive: ' . $e->getMessage(),
                ], 502);
            }
        }

        try {
            $serviceAccount->shareFolder($audioFolderId, $accountEmail);
        } catch (GoogleServiceException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'already')) {
                Log::error('saveResults share failure', [
                    'error'                 => $e->getMessage(),
                    'transcription_folder'  => $transcriptionFolderId,
                    'audio_folder'          => $audioFolderId,
                    'service_account_email' => $accountEmail,
                ]);

                return response()->json([
                    'message' => 'Error de Drive: ' . $e->getMessage(),
                ], 502);
            }
        }

        try {
            // 2. Carpetas en Drive
            $meetingName = $v['meetingName'];

            $audioFile = $request->file('audioFile');
            $tmp = null;
            if ($audioFile instanceof UploadedFile) {
                if ($audioFile->getSize() > $maxAudioBytes) {
                    return response()->json([
                        'message' => 'Archivo de audio demasiado grande (máx. 200 MB)',
                    ], 422);
                }

                $tmp = tempnam(sys_get_temp_dir(), 'aud');
                file_put_contents($tmp, file_get_contents($audioFile->getRealPath()));
                $audioMime = strtolower($audioFile->getMimeType() ?: $audioFile->getClientMimeType() ?: ($v['audioMimeType'] ?? ''));
            } else {
                // 3. Decodifica Base64
                $b64    = $v['audioData'];
                if (str_contains($b64, ',')) {
                    [, $b64] = explode(',', $b64, 2);
                }
                $raw    = base64_decode($b64);
                if ($raw === false) {
                    return response()->json([
                        'message' => 'Audio inválido o corrupto',
                    ], 422);
                }
                if (strlen($raw) > $maxAudioBytes) {
                    return response()->json([
                        'message' => 'Archivo de audio demasiado grande (máx. 200 MB)',
                    ], 422);
                }

                $tmp   = tempnam(sys_get_temp_dir(), 'aud');
                file_put_contents($tmp, $raw);
                $audioMime = strtolower($v['audioMimeType']);
            }

            if (empty($audioMime)) {
                $audioMime = 'audio/ogg';
            }

            // Posible conversión a OGG (política: subir solo OGG)
            $converted = null;
            if (config('audio.force_ogg')) {
                try {
                    $conversionService = app(\App\Services\AudioConversionService::class);
                    $extOrig = pathinfo($tmp, PATHINFO_EXTENSION) ?: null; // probablemente vacío
                    $converted = $conversionService->convertToOgg($tmp, $audioMime, $extOrig);
                    if ($converted['was_converted']) {
                        $tmp = $converted['path'];
                        $audioMime = $converted['mime_type'];
                        Log::info('saveResults: audio converted to ogg', ['meeting' => $v['meetingName']]);
                    } else {
                        $alreadyOgg = str_contains(strtolower($audioMime), 'ogg') || str_ends_with(strtolower($tmp), '.ogg');
                        if (!$alreadyOgg) {
                            return response()->json([
                                'code' => 'OGG_REQUIRED',
                                'message' => 'La conversión a OGG es obligatoria y no se pudo completar.',
                            ], 500);
                        }
                    }
                } catch (\App\Exceptions\FfmpegUnavailableException $e) {
                    Log::warning('saveResults: ffmpeg unavailable - OGG required policy', ['error' => $e->getMessage()]);
                    return response()->json([
                        'code' => 'FFMPEG_UNAVAILABLE',
                        'message' => 'FFmpeg no está disponible en el servidor. La conversión a OGG es obligatoria. Instala ffmpeg o desactiva AUDIO_FORCE_OGG para desarrollo.',
                    ], 500);
                } catch (\Throwable $e) {
                    Log::error('saveResults: ogg conversion failed (policy requires OGG)', ['error' => $e->getMessage()]);
                    return response()->json([
                        'code' => 'OGG_CONVERSION_FAILED',
                        'message' => 'Falló la conversión a OGG. Intenta con otro archivo o contacta al administrador.',
                    ], 500);
                }
            }

            // 5. Prepara payload de transcripción/análisis
            $analysis = is_array($v['analysisResults']) ? $v['analysisResults'] : [];
            $payload  = [
                'segments'  => $v['transcriptionData'],
                'summary'   => $analysis['summary']   ?? null,
                'keyPoints' => $analysis['keyPoints'] ?? [],
            ];
            $encrypted = Crypt::encryptString(json_encode($payload));

            // 6. Sube los archivos a Drive usando la cuenta de servicio
            try {
                $transcriptFileId = $serviceAccount
                    ->uploadFile("{$meetingName}.ju", 'application/json', $transcriptionFolderId, $encrypted);

                // extrae la extensión a partir del mimeType usando un mapa conocido
                $mime = $audioMime;
                if ($converted && $converted['was_converted']) {
                    $mime = 'audio/ogg';
                }
                $baseMime = explode(';', $mime)[0];
                $ext      = $mimeToExt[$baseMime]
                    ?? preg_replace('/[^\\w]/', '', explode('/', $baseMime, 2)[1] ?? '');

                $audioFileId = $serviceAccount
                    ->uploadFile("{$meetingName}.{$ext}", $mime, $audioFolderId, $tmp);
                @unlink($tmp);
                if ($converted && $converted['was_converted']) {
                    @unlink($converted['path']);
                }
            } catch (GoogleServiceException $e) {
                Log::error('saveResults drive failure', [
                    'error'                  => $e->getMessage(),
                    'code'                   => $e->getCode(),
                    'transcription_folder'   => $transcriptionFolderId,
                    'audio_folder'           => $audioFolderId,
                    'service_account_email'  => $accountEmail,
                ]);

                if (
                    $e->getCode() === 404 ||
                    $e->getCode() === 403 ||
                    str_contains($e->getMessage(), 'File not found') ||
                    str_contains($e->getMessage(), 'The caller does not have permission')
                ) {
                    return response()->json([
                        'code'    => 'FOLDER_NOT_SHARED',
                        'message' => "La carpeta no está compartida con la cuenta de servicio. Comparte la carpeta con {$accountEmail}",
                    ], 403);
                }

                return response()->json([
                    'message' => 'Error de Drive: ' . $e->getMessage(),
                ], 502);
            } catch (RuntimeException $e) {
                Log::error('saveResults drive failure', [
                    'error'                 => $e->getMessage(),
                    'transcription_folder'  => $transcriptionFolderId,
                    'audio_folder'          => $audioFolderId,
                    'service_account_email' => $accountEmail,
                ]);
                return response()->json([
                    'message' => 'Error de Drive: ' . $e->getMessage(),
                ], 502);
            }

            $transcriptUrl = $serviceAccount->getFileLink($transcriptFileId);
            $audioUrl      = $serviceAccount->getFileLink($audioFileId);

            // 7. Calcula información adicional
            $rootName = $rootFolder->name ?? '';
            $drivePath = $rootName;

            // Con estructura fija ya no se usa subfolderId dinámico para path detallado.
            $drivePath .= '/Transcripciones';

            $duration = 0;
            $speakers = [];
            foreach ($v['transcriptionData'] as $seg) {
                if (isset($seg['end']) && $seg['end'] > $duration) {
                    $duration = $seg['end'];
                }
                if (!empty($seg['speaker'])) {
                    $speakers[$seg['speaker']] = true;
                }
            }
            $speakerCount = count($speakers);

            // 8. Guarda en BD y responde
            try {
                $meeting = TranscriptionLaravel::create([
                    'username'               => Auth::user()->username,
                    'meeting_name'           => $meetingName,
                    'audio_drive_id'         => $audioFileId,
                    'audio_download_url'     => $audioUrl,
                    'transcript_drive_id'    => $transcriptFileId,
                    'transcript_download_url' => $transcriptUrl,
                ]);

                // Registrar actividad de organización si aplica
                try {
                    $actor = Auth::user();
                    $orgId = $actor->current_organization_id;
                    if ($orgId) {
                        // Calcular restantes según límites compartidos
                        $planService = app(PlanLimitService::class);
                        $limits = $planService->getLimitsForUser($actor);
                        $remaining = $limits['remaining'];
                        OrganizationActivity::create([
                            'organization_id' => $orgId,
                            'group_id' => null,
                            'container_id' => null,
                            'user_id' => $actor->id,
                            'target_user_id' => null,
                            'action' => 'meeting_recorded',
                            'description' => sprintf('%s grabó una reunión. Reuniones restantes este mes: %s', $actor->full_name ?? $actor->username, is_null($remaining) ? '∞' : $remaining),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Organization activity log failed (DriveController)', ['error' => $e->getMessage()]);
                }

                $savedTasks = [];
                foreach ($analysis['tasks'] ?? [] as $rawTask) {
                    // Prefer direct mapping (text/context/assignee/dueDate) but fallback to parser too
                    $mappedTarea = $rawTask['text'] ?? null;
                    $mappedDesc  = $rawTask['context'] ?? null;
                    $mappedAsig  = $rawTask['assignee'] ?? null;
                    $mappedStart = $rawTask['dueDate'] ?? null;

                    $fechaInicio = null;
                    if (is_string($mappedStart)) {
                        $s = trim($mappedStart);
                        if ($s !== '' && strtolower($s) !== 'no definida' && strtolower($s) !== 'no asignado') {
                            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
                                $fechaInicio = $m[3] . '-' . $m[2] . '-' . $m[1];
                            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                                $fechaInicio = $s;
                            }
                        }
                    }

                    if (!$mappedTarea) {
                        $parsed = $this->parseRawTaskForDb($rawTask);
                        $mappedTarea = $parsed['tarea'];
                        $mappedDesc  = $mappedDesc ?? $parsed['descripcion'];
                        $fechaInicio = $fechaInicio ?? $parsed['fecha_inicio'];
                        $mappedAsig  = $mappedAsig ?? $parsed['asignado'] ?? null;
                    }

                    $taskModel = TaskLaravel::updateOrCreate(
                        [
                            'username'   => Auth::user()->username,
                            'meeting_id' => $meeting->id,
                            'tarea'      => substr((string)$mappedTarea, 0, 255),
                        ],
                        [
                            'descripcion'  => $mappedDesc ?: '',
                            'asignado'     => $mappedAsig,
                            'fecha_inicio' => $fechaInicio,
                            'fecha_limite' => null,
                            'progreso'     => 0,
                        ]
                    );
                    $savedTasks[] = $taskModel;
                }
            } catch (\Throwable $e) {
                Log::error('saveResults db failure', [
                    'error'                 => $e->getMessage(),
                    'transcription_folder'  => $transcriptionFolderId,
                    'audio_folder'          => $audioFolderId,
                    'service_account_email' => $accountEmail,
                ]);
                return response()->json([
                    'message' => 'Error de base de datos: ' . $e->getMessage(),
                ], 500);
            }

            return response()->json([
                'saved'                   => true,
                'audio_drive_id'          => $audioFileId,
                'audio_download_url'      => $audioUrl,
                'transcript_drive_id'     => $transcriptFileId,
                'transcript_download_url' => $transcriptUrl,
                'drive_path'              => $drivePath,
                'audio_duration'          => $duration,
                'speaker_count'           => $speakerCount,
                'tasks'                   => $savedTasks ?? [],
            ], 200);

        } catch (RuntimeException $e) {
            Log::error('saveResults failed', [
                'error'                 => $e->getMessage(),
                'transcription_folder'  => $transcriptionFolderId,
                'audio_folder'          => $audioFolderId,
                'service_account_email' => $accountEmail,
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('saveResults failed', [
                'exception'             => $e->getMessage(),
                'transcription_folder'  => $transcriptionFolderId,
                'audio_folder'          => $audioFolderId,
                'service_account_email' => $accountEmail,
            ]);

            if (str_contains($e->getMessage(), 'unauthorized_client')) {
                return response()->json([
                    'message' => 'La cuenta de servicio no está autorizada para acceder a Google Drive'
                ], 403);
            }

            if (
                str_contains($e->getMessage(), 'File not found') ||
                str_contains($e->getMessage(), 'The caller does not have permission')
            ) {
                return response()->json([
                    'code'    => 'FOLDER_NOT_SHARED',
                    'message' => "La carpeta no está compartida con la cuenta de servicio. Comparte la carpeta con {$accountEmail}",
                ], 403);
            }

            return response()->json(['message' => 'Error interno'], 500);
        }
    }
}
