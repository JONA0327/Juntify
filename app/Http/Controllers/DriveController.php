<?php

namespace App\Http\Controllers;

// (El bloque que estaba antes de <?php generaba salida cruda y rompía JSON; se ha movido dentro de la clase)

use App\Traits\EnsuresStandardSubfolders;
use App\Traits\MeetingContentParsing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Google\Service\Drive as DriveService;
use Google\Service\Exception as GoogleServiceException;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Models\OrganizationActivity;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\UploadedFile;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\GoogleToken;
use Illuminate\Http\Request;
use App\Models\PendingRecording;
use App\Models\Notification;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use App\Models\TranscriptionLaravel;
use App\Models\TaskLaravel;
use Illuminate\Support\Str;
use App\Models\TranscriptionTemp;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DriveController extends Controller
{
    use MeetingContentParsing;
    use EnsuresStandardSubfolders;

    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
        Log::info('DriveController constructor called', ['user' => Auth::user() ? Auth::user()->username : null]);
    }

    /**
     * Intenta asegurar que una subcarpeta esté compartida con la service account.
     * Estrategia:
     *  1. Intentar directamente con la service account.
     *  2. Si 403/404 (not found / no permission) y es drive personal:
     *     a) Si tenemos token OAuth del usuario: usar GoogleDriveService->ensureSharedWithServiceAccount
     *     b) Como alternativa final: impersonar ownerEmail y compartir.
     *  3. Si 404 real (carpeta eliminada) intentar recrearla (solo si tenemos rootFolder y nombre esperado opcional).
     * Devuelve true si quedó compartida.
     */
    private function attemptShareSubfolder(
        string $folderId,
        bool $useOrgDrive,
        GoogleServiceAccount $serviceAccount,
        string $serviceEmail,
        ?User $user,
        ?\App\Models\GoogleToken $userToken,
        $rootFolder,
        ?string $expectedName = null
    ): bool {
        try {
            $serviceAccount->shareFolder($folderId, $serviceEmail);
            return true;
        } catch (GoogleServiceException $e) {
            $code = (int)$e->getCode();
            $msg  = strtolower($e->getMessage());
            $permissionIssue = in_array($code, [403, 404]) || str_contains($msg, 'file not found') || str_contains($msg, 'not have permission');
            if (!$permissionIssue) {
                Log::error('attemptShareSubfolder: unexpected share error', [
                    'folder_id' => $folderId,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            if ($useOrgDrive) {
                // Para drive organizacional, normalmente la service account debería ver el folder.
                Log::warning('attemptShareSubfolder: permission issue in org drive', [
                    'folder_id' => $folderId,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            // Drive personal: intentar con token OAuth
            if ($userToken && $userToken->hasValidAccessToken()) {
                try {
                    /** @var \App\Services\GoogleDriveService $driveOAuth */
                    $driveOAuth = app(\App\Services\GoogleDriveService::class);
                    $driveOAuth->setAccessToken($userToken->getTokenArray());
                    if ($driveOAuth->ensureSharedWithServiceAccount($folderId, $serviceEmail)) {
                        Log::info('attemptShareSubfolder: shared via OAuth fallback', [
                            'folder_id' => $folderId,
                        ]);
                        return true;
                    }
                } catch (\Throwable $e2) {
                    Log::warning('attemptShareSubfolder: OAuth fallback failed', [
                        'folder_id' => $folderId,
                        'error' => $e2->getMessage(),
                    ]);
                }
            }

            // Intentar impersonation si conocemos el email del owner
            $ownerEmail = $this->resolveRootOwnerEmail($rootFolder, false);
            if ($ownerEmail && !GoogleServiceAccount::impersonationDisabled()) {
                try {
                    $serviceAccount->impersonate($ownerEmail);
                    $serviceAccount->shareFolder($folderId, $serviceEmail);
                    Log::info('attemptShareSubfolder: shared via impersonation', [
                        'folder_id' => $folderId,
                        'owner' => $ownerEmail,
                    ]);
                    // Reset impersonation
                    $serviceAccount->impersonate(null);
                    return true;
                } catch (\Throwable $e3) {
                    Log::warning('attemptShareSubfolder: impersonation share failed', [
                        'folder_id' => $folderId,
                        'owner' => $ownerEmail,
                        'error' => $e3->getMessage(),
                    ]);
                    $serviceAccount->impersonate(null);
                }
            }

            // Verificar si la carpeta realmente existe usando impersonation (si posible) o recrear
            try {
                $exists = false;
                try {
                    $serviceAccount->impersonate($ownerEmail);
                    $serviceAccount->getFileInfo($folderId);
                    $exists = true;
                } catch (\Throwable $ff) {
                    $exists = false;
                } finally {
                    $serviceAccount->impersonate(null);
                }
                if (!$exists && $expectedName && $rootFolder && !empty($rootFolder->google_id)) {
                    try {
                        $serviceAccount->impersonate($ownerEmail);
                        $newId = $serviceAccount->createFolder($expectedName, $rootFolder->google_id);
                        $serviceAccount->shareFolder($newId, $serviceEmail); // comparte mientras impersona
                        $serviceAccount->impersonate(null);
                        // Actualizar modelo BD para reflejar nuevo ID
                        if (!$useOrgDrive) {
                            \App\Models\Subfolder::where('google_id', $folderId)->update(['google_id' => $newId]);
                        } else {
                            \App\Models\OrganizationSubfolder::where('google_id', $folderId)->update(['google_id' => $newId]);
                        }
                        Log::info('attemptShareSubfolder: recreated missing folder', [
                            'old_id' => $folderId,
                            'new_id' => $newId,
                            'expected_name' => $expectedName,
                        ]);
                        return true;
                    } catch (\Throwable $re) {
                        Log::error('attemptShareSubfolder: recreation failed', [
                            'old_id' => $folderId,
                            'expected_name' => $expectedName,
                            'error' => $re->getMessage(),
                        ]);
                        $serviceAccount->impersonate(null);
                    }
                }
            } catch (\Throwable $final) {
                Log::debug('attemptShareSubfolder: existence check/recreate step failed', [
                    'folder_id' => $folderId,
                    'error' => $final->getMessage(),
                ]);
            }
        } catch (\Throwable $generic) {
            Log::error('attemptShareSubfolder: unexpected throwable', [
                'folder_id' => $folderId,
                'error' => $generic->getMessage(),
            ]);
        }
        return false;
    }

    public function createMainFolder(Request $request)
    {
        return response()->json([
            'deprecated' => true,
            'message' => 'La creación manual de la carpeta principal fue deshabilitada. Juntify crea una carpeta automática al conectar Drive. Si deseas establecer una carpeta raíz diferente, usa "Establecer carpeta" e ingresa el número de la carpeta.'
        ], 410);
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
            'message'    => 'Juntify ya establece una carpeta automática donde guarda tus reuniones. Si deseas establecer una carpeta raíz diferente, ingresa el número de la carpeta.',
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
        // Ahora además garantizamos y devolvemos las subcarpetas estándar bajo la raíz personal.
        $username = Auth::user()->username;
        $token = GoogleToken::where('username', $username)->first();
        if (!$token) {
            return response()->json([
                'root_folder' => null,
                'subfolders'  => [],
                'message'     => 'Token no encontrado',
                'deprecated'  => false,
            ], 200);
        }

        $rootFolder = Folder::where('google_token_id', $token->id)
            ->whereNull('parent_id')
            ->first();

        if (!$rootFolder) {
            return response()->json([
                'root_folder' => null,
                'subfolders'  => [],
                'message'     => 'Carpeta raíz personal no configurada',
                'deprecated'  => false,
            ], 200);
        }

        // Garantizar subcarpetas estándar con la cuenta de servicio (si faltan, crearlas dentro de la raíz)
        try {
            /** @var GoogleServiceAccount $serviceAccount */
            $serviceAccount = app(GoogleServiceAccount::class);
            $this->ensureStandardSubfolders($rootFolder, false, $serviceAccount);
        } catch (\Throwable $e) {
            Log::warning('syncDriveSubfolders: ensureStandardSubfolders failed', [
                'root_folder_id' => $rootFolder->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Devolver el estado actualizado desde BD para evitar llamadas adicionales a Drive
        $subfolders = Subfolder::where('folder_id', $rootFolder->id)
            ->orderBy('name')
            ->get()
            ->values();

        return response()->json([
            'root_folder' => $rootFolder,
            'subfolders'  => $subfolders,
            'deprecated'  => false,
            'message'     => 'Subcarpetas sincronizadas automáticamente bajo la carpeta raíz personal.'
        ], 200);
    }
    public function status()
    {
        $username = Auth::user()->username;
        $token = GoogleToken::where('username', $username)->first();
        if (! $token) {
            return response()->json([
                'connected' => false,
                'message'   => 'No hay token de Google Drive vinculado a tu cuenta.',
            ], 200);
        }

        $rootFolder = null;
        if (!empty($token->recordings_folder_id)) {
            $rootFolder = Folder::where('google_token_id', $token->id)
                ->whereNull('parent_id')
                ->where('google_id', $token->recordings_folder_id)
                ->first();
        }
        if (! $rootFolder) {
            // fallback: primer folder raíz asociado al token
            $rootFolder = Folder::where('google_token_id', $token->id)
                ->whereNull('parent_id')
                ->first();
        }

        $defaultRootName = config('drive.default_root_folder_name', 'Juntify Recordings');
        $missing = [];
        if ($rootFolder) {
            $have = Subfolder::where('folder_id', $rootFolder->id)->pluck('name')->map(fn ($n) => strtolower($n))->all();
            $needed = ['Audios', 'Transcripciones', 'Audios Pospuestos', 'Documentos'];
            foreach ($needed as $name) {
                if (! in_array(strtolower($name), $have, true)) {
                    $missing[] = $name;
                }
            }
        }

        return response()->json([
            'connected'          => true,
            'root_folder'        => $rootFolder?->name,
            'root_folder_id'     => $rootFolder?->google_id,
            'default_root_name'  => $defaultRootName,
            'missing_subfolders' => $missing,
        ], 200);
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

                // Resolución del root personal (prioridad): recordings_folder_id del token -> rootFolder parámetro -> primer root en BD
                $rootFolder = null;
                if (!empty($token->recordings_folder_id)) {
                    $rootFolder = Folder::where('google_token_id', $token->id)
                        ->where('google_id', $token->recordings_folder_id)
                        ->whereNull('parent_id')
                        ->first();
                }
                if (!$rootFolder && !empty($v['rootFolder'])) {
                    $rootFolder = Folder::where('google_token_id', $token->id)
                        ->whereNull('parent_id')
                        ->where(function ($q) use ($v) {
                            $q->where('google_id', $v['rootFolder'])
                              ->orWhere('id', $v['rootFolder']);
                        })
                        ->first();
                }
                if (!$rootFolder) {
                    $rootFolder = Folder::where('google_token_id', $token->id)
                        ->whereNull('parent_id')
                        ->first();
                }

                if (! $rootFolder) {
                    // Try to create a default root folder if none exists
                    Log::info('uploadPendingAudio: creating default root folder for user', [
                        'username' => $user->username,
                    ]);

                    try {
                        // Create a default recordings root folder (configurable)
                        $defaultFolderName = config('drive.default_root_folder_name', 'Juntify Recordings');

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
                'user_id'   => $user->id,
                'from_user_id' => $user->id,
                'status'    => 'pending',
                'message'   => 'Subida iniciada',
                'type'      => 'audio_upload',
                'title'     => 'Subida de audio pendiente',
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
                    'root_folder' => $rootFolder->name ?? config('drive.default_root_folder_name', 'Juntify Recordings'),
                    'subfolder'   => $pendingSubfolderName,
                    'full_path'   => ($rootFolder->name ?? config('drive.default_root_folder_name', 'Juntify Recordings')) . '/' . $pendingSubfolderName,
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

    public function initChunkedAudioSave(Request $request)
    {
        $v = $request->validate([
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'size' => 'required|integer|min:1',
            'chunks' => 'required|integer|min:1',
        ]);

        $uploadId = (string) Str::uuid();
        $baseDir = $this->getChunkedAudioBasePath($uploadId);
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            return response()->json([
                'message' => 'No se pudo preparar el almacenamiento temporal para el audio',
            ], 500);
        }

        $metadata = [
            'filename' => $v['filename'],
            'mime_type' => strtolower($v['mime_type']),
            'total_size' => (int) $v['size'],
            'chunks_expected' => (int) $v['chunks'],
            'chunks_received' => 0,
            'received_indices' => [],
            'created_at' => now()->toIso8601String(),
        ];

        file_put_contents($this->getChunkedAudioMetadataPath($uploadId), json_encode($metadata));

        return response()->json([
            'upload_id' => $uploadId,
        ]);
    }

    public function uploadChunkedAudioSave(Request $request)
    {
        set_time_limit(300);

        $v = $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
            'upload_id' => 'required|string',
        ]);

        $uploadId = $v['upload_id'];
        $metadataPath = $this->getChunkedAudioMetadataPath($uploadId);
        if (!file_exists($metadataPath)) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        $chunk = $request->file('chunk');
        $chunkDir = dirname($metadataPath);
        $chunk->move($chunkDir, 'chunk_' . $v['chunk_index']);

        $this->updateChunkedAudioMetadata($metadataPath, function (&$metadata) use ($v) {
            $metadata['received_indices'] = $metadata['received_indices'] ?? [];
            if (!in_array($v['chunk_index'], $metadata['received_indices'], true)) {
                $metadata['received_indices'][] = $v['chunk_index'];
                sort($metadata['received_indices']);
                $metadata['chunks_received'] = count($metadata['received_indices']);
            }
        });

        return response()->json(['success' => true]);
    }

    public function finalizeChunkedAudioSave(Request $request)
    {
        set_time_limit(900);

        $v = $request->validate([
            'upload_id' => 'required|string',
        ]);

        $uploadId = $v['upload_id'];
        $metadataPath = $this->getChunkedAudioMetadataPath($uploadId);
        if (!file_exists($metadataPath)) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
        $expected = (int) ($metadata['chunks_expected'] ?? 0);

        $chunkDir = dirname($metadataPath);
        $files = glob($chunkDir . '/chunk_*');
        $present = is_array($files) ? count($files) : 0;
        $missing = [];
        for ($i = 0; $i < $expected; $i++) {
            $path = $chunkDir . '/chunk_' . $i;
            if (!file_exists($path)) {
                $missing[] = $i;
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'error' => 'missing_chunks',
                'expected' => $expected,
                'present_count' => $present,
                'missing_indices' => $missing,
            ], 400);
        }

        $extension = pathinfo($metadata['filename'] ?? '', PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        $finalPath = $chunkDir . '/final_audio' . $extension;

        $dest = fopen($finalPath, 'wb');
        if (!$dest) {
            return response()->json([
                'message' => 'No se pudo combinar el audio en el servidor',
            ], 500);
        }

        for ($i = 0; $i < $expected; $i++) {
            $chunkPath = $chunkDir . '/chunk_' . $i;
            $source = fopen($chunkPath, 'rb');
            if (!$source) {
                fclose($dest);
                return response()->json([
                    'message' => 'No se pudo leer un fragmento de audio',
                ], 500);
            }
            stream_copy_to_stream($source, $dest);
            fclose($source);
            @unlink($chunkPath);
        }

        fflush($dest);
        fclose($dest);

        $metadata['chunks_received'] = $expected;
        $metadata['combined_size'] = filesize($finalPath);
        $metadata['final_path'] = $finalPath;
        $metadata['final_filename'] = basename($finalPath);
        $metadata['combined_at'] = now()->toIso8601String();
        file_put_contents($metadataPath, json_encode($metadata));

        return response()->json([
            'upload_id' => $uploadId,
            'final_size' => $metadata['combined_size'],
            'mime_type' => $metadata['mime_type'] ?? null,
        ]);
    }
    public function saveResults(Request $request)
    {
        Log::info('saveResults reached', ['user' => Auth::user() ? Auth::user()->username : null]);
        Log::debug('saveResults before validation', [
            'meetingName' => $request->input('meetingName'),
            'audioLength' => strlen($request->input('audioData', '')),
            'audioUploadId' => $request->input('audioUploadId'),
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
            'audioData'              => 'required_without_all:audioFile,audioUploadId|string|max:' . (int) ceil($maxAudioBytes * 4 / 3),      // Base64 (~266MB bruto)
            'audioMimeType'          => 'required_without_all:audioFile,audioUploadId|string',      // p.ej. "audio/webm"
            'audioFile'              => 'required_without_all:audioData,audioUploadId|file|mimetypes:' . $allowedAudioMimes . '|max:204800',
            'audioUploadId'          => 'required_without_all:audioFile,audioData|string',
            'driveType'              => 'nullable|string|in:personal,organization', // Nuevo campo para tipo de drive
        ], [
            'audioData.max' => 'Archivo de audio demasiado grande (máx. 200 MB)',
        ]);

        $audioUploadId = $v['audioUploadId'] ?? null;
        $cleanupChunkedUpload = function () use ($audioUploadId) {
            if ($audioUploadId) {
                $this->cleanupChunkedAudioUpload($audioUploadId);
            }
        };
        $respondWithCleanup = function (array $payload, int $status = 200) use ($cleanupChunkedUpload) {
            $cleanupChunkedUpload();
            return response()->json($payload, $status);
        };

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
            return $respondWithCleanup([
                'message' => 'Transcripción inválida',
            ], 422);
        }

        if (!is_array($v['analysisResults'])) {
            if (is_null($v['analysisResults'])) {
                $v['analysisResults'] = [];
            } else {
                return $respondWithCleanup([
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
                return $respondWithCleanup([
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
        $planService = app(PlanLimitService::class);
        $canUseDrive = $planService->userCanUseDrive($user);
        $organizationFolder = $user->organizationFolder;
        $token = GoogleToken::where('username', $user->username)->first();

        // Determinar si usar Drive organizacional basado en driveType
        $driveType = $v['driveType'] ?? 'personal';
        $useOrgDrive = false;

        Log::info('saveResults: Drive type selection', [
            'driveType' => $driveType,
            'hasOrganizationFolder' => !!$organizationFolder,
            'username' => $user->username,
            'rootFolder_param' => $v['rootFolder'] ?? null
        ]);

        $hasDriveConnection = $driveType === 'organization' ? (bool) $organizationFolder : (bool) $token;
        if (!$canUseDrive || !$hasDriveConnection) {
            return $this->storeTemporaryResult(
                $user,
                $v,
                $request,
                $respondWithCleanup,
                $cleanupChunkedUpload,
                $mimeToExt,
                $maxAudioBytes,
                [
                    'reason' => $canUseDrive ? 'drive_not_connected' : 'plan_restricted',
                    'drive_type' => $driveType,
                ]
            );
        }

        if ($driveType === 'organization' && $organizationFolder) {
            $useOrgDrive = true;
            Log::info('saveResults: Using organization drive', [
                'orgFolderId' => $organizationFolder->google_id
            ]);
        }

        if ($useOrgDrive) {
            $rootFolder = $organizationFolder; // OrganizationFolder model
        } else {
            if (!$token) {
                return $this->storeTemporaryResult(
                    $user,
                    $v,
                    $request,
                    $respondWithCleanup,
                    $cleanupChunkedUpload,
                    $mimeToExt,
                    $maxAudioBytes,
                    [
                        'reason' => $canUseDrive ? 'drive_not_connected' : 'plan_restricted',
                        'drive_type' => $driveType,
                    ]
                );
            }

            // Resolución del root personal (prioridad): recordings_folder_id del token -> rootFolder parámetro -> primer root en BD
            $rootFolder = null;
            if (!empty($token->recordings_folder_id)) {
                $rootFolder = Folder::where('google_token_id', $token->id)
                    ->where('google_id', $token->recordings_folder_id)
                    ->whereNull('parent_id')
                    ->first();
            }
            if (!$rootFolder && !empty($v['rootFolder'])) {
                $rootFolder = Folder::where('google_token_id', $token->id)
                    ->where(function($q) use ($v) {
                        $q->where('google_id', $v['rootFolder'])
                          ->orWhere('id', $v['rootFolder']);
                    })
                    ->whereNull('parent_id')
                    ->first();
            }
            if (!$rootFolder) {
                // Autodetectar primera carpeta raíz del usuario
                $rootFolder = Folder::where('google_token_id', $token->id)
                    ->whereNull('parent_id')
                    ->first();
            }
            if (!$rootFolder) {
                return $this->storeTemporaryResult(
                    $user,
                    $v,
                    $request,
                    $respondWithCleanup,
                    $cleanupChunkedUpload,
                    $mimeToExt,
                    $maxAudioBytes
                );
            }
        }

        // Asegurar que la cuenta de servicio tiene acceso de escritura al folder raíz antes de crear subcarpetas
        $serviceEmail = config('services.google.service_account_email');
        $serviceAccount = app(GoogleServiceAccount::class);
        try {
            $serviceAccount->shareFolder($rootFolder->google_id, $serviceEmail);
        } catch (GoogleServiceException $e) {
            $needsShare = (
                $e->getCode() === 404 ||
                $e->getCode() === 403 ||
                str_contains($e->getMessage(), 'File not found') ||
                str_contains($e->getMessage(), 'The caller does not have permission')
            );
            if ($needsShare) {
                $shared = false;

                // Fallback: intentar compartir con token del usuario
                try {
                    /** @var \App\Services\GoogleDriveService $driveOAuth */
                    $driveOAuth = app(\App\Services\GoogleDriveService::class);
                    $userToken = \App\Models\GoogleToken::where('username', $user->username)->first();
                    if ($userToken && $userToken->hasValidAccessToken()) {
                        $driveOAuth->setAccessToken($userToken->getTokenArray());
                        if ($driveOAuth->ensureSharedWithServiceAccount($rootFolder->google_id, $serviceEmail)) {
                            Log::info('saveResults: folder auto-shared with service account via OAuth fallback');
                            $shared = true;
                        }
                    }
                } catch (\Throwable $e2) {
                    Log::error('saveResults: auto-share fallback failed', [
                        'error' => $e2->getMessage(),
                    ]);
                }

                // Fallback adicional: intentar impersonar al propietario del folder raíz
                if (!$shared) {
                    $ownerEmail = $this->resolveRootOwnerEmail($rootFolder, $useOrgDrive);
                    if ($ownerEmail && !GoogleServiceAccount::impersonationDisabled()) {
                        try {
                            $serviceAccount->impersonate($ownerEmail);
                            $serviceAccount->shareFolder($rootFolder->google_id, $serviceEmail);
                            $shared = true;
                            Log::info('saveResults: root folder shared via impersonation fallback', [
                                'owner' => $ownerEmail,
                            ]);
                        } catch (\Throwable $impersonationError) {
                            Log::warning('saveResults: impersonation fallback failed', [
                                'owner' => $ownerEmail,
                                'error' => $impersonationError->getMessage(),
                            ]);
                        } finally {
                            try {
                                $serviceAccount->impersonate(null);
                            } catch (\Throwable $resetError) {
                                Log::debug('saveResults: failed to reset impersonation after root share attempt', [
                                    'error' => $resetError->getMessage(),
                                ]);
                            }
                        }
                    }
                }

                if (!$shared) {
                    return $respondWithCleanup([
                        'code'    => 'FOLDER_NOT_SHARED',
                        'message' => "La carpeta principal no está compartida con la cuenta de servicio. Comparte la carpeta con {$serviceEmail}",
                    ], 403);
                }
            }
        }

        // Garantizar subcarpetas estándar dentro del folder raíz seleccionado
        $standard = $this->ensureStandardSubfolders($rootFolder, $useOrgDrive, $serviceAccount);
        $audioModel = $standard['audio'] ?? null;
        $transModel = $standard['transcription'] ?? null;
        if (!$audioModel || !$transModel) {
            return $respondWithCleanup([
                'message' => 'No se pudieron preparar las subcarpetas estándar (Audios/Transcripciones) dentro de la carpeta raíz. Verifica permisos de la cuenta de servicio.'
            ], 500);
        }
        $audioFolderId = $audioModel->google_id;
        $transcriptionFolderId = $transModel->google_id;

        $accountEmail = $serviceEmail;

        // Compartir subcarpetas con fallback robusto
        $userTokenModel = isset($token) ? $token : (isset($user) ? GoogleToken::where('username', $user->username)->first() : null);
        if (!$this->attemptShareSubfolder($transcriptionFolderId, $useOrgDrive, $serviceAccount, $accountEmail, $user, $userTokenModel, $rootFolder, 'Transcripciones')) {
            return $respondWithCleanup([
                'code' => 'FOLDER_NOT_SHARED',
                'message' => "No se pudo compartir la carpeta de transcripciones con la cuenta de servicio. Comparte manualmente con {$accountEmail}",
            ], 403);
        }
        if (!$this->attemptShareSubfolder($audioFolderId, $useOrgDrive, $serviceAccount, $accountEmail, $user, $userTokenModel, $rootFolder, 'Audios')) {
            return $respondWithCleanup([
                'code' => 'FOLDER_NOT_SHARED',
                'message' => "No se pudo compartir la carpeta de audios con la cuenta de servicio. Comparte manualmente con {$accountEmail}",
            ], 403);
        }

        try {
            // 2. Carpetas en Drive
            $meetingName = $v['meetingName'];

        $audioUploadId = $v['audioUploadId'] ?? null;
        $audioFile = $request->file('audioFile');
        $tmp = null;
        if ($audioUploadId) {
            $resolvedUpload = $this->resolveChunkedAudioUpload($audioUploadId);
            if (!$resolvedUpload) {
                return $respondWithCleanup([
                    'message' => 'El archivo de audio temporal no está disponible o expiró. Vuelve a subir el audio e inténtalo de nuevo.',
                ], 422);
            }

            $sourcePath = $resolvedUpload['path'];
            if (filesize($sourcePath) > $maxAudioBytes) {
                return $respondWithCleanup([
                    'message' => 'Archivo de audio demasiado grande (máx. 200 MB)',
                ], 422);
            }

            $tmp = tempnam(sys_get_temp_dir(), 'aud');
            if (!@copy($sourcePath, $tmp)) {
                return $respondWithCleanup([
                    'message' => 'No se pudo preparar el audio temporal',
                ], 500);
            }
            $audioMime = $resolvedUpload['mime'] ?: strtolower($v['audioMimeType'] ?? '');
        } elseif ($audioFile instanceof UploadedFile) {
            if ($audioFile->getSize() > $maxAudioBytes) {
                return $respondWithCleanup([
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
                    return $respondWithCleanup([
                        'message' => 'Audio inválido o corrupto',
                    ], 422);
                }
                if (strlen($raw) > $maxAudioBytes) {
                    return $respondWithCleanup([
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
                            return $respondWithCleanup([
                                'code' => 'OGG_REQUIRED',
                                'message' => 'La conversión a OGG es obligatoria y no se pudo completar.',
                            ], 500);
                        }
                    }
                } catch (\App\Exceptions\FfmpegUnavailableException $e) {
                    Log::warning('saveResults: ffmpeg unavailable - OGG required policy', ['error' => $e->getMessage()]);
                    return $respondWithCleanup([
                        'code' => 'FFMPEG_UNAVAILABLE',
                        'message' => 'FFmpeg no está disponible en el servidor. La conversión a OGG es obligatoria. Instala ffmpeg o desactiva AUDIO_FORCE_OGG para desarrollo.',
                    ], 500);
                } catch (\Throwable $e) {
                    Log::error('saveResults: ogg conversion failed (policy requires OGG)', ['error' => $e->getMessage()]);
                    return $respondWithCleanup([
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
                    return $respondWithCleanup([
                        'code'    => 'FOLDER_NOT_SHARED',
                        'message' => "La carpeta no está compartida con la cuenta de servicio. Comparte la carpeta con {$accountEmail}",
                    ], 403);
                }

                return $respondWithCleanup([
                    'message' => 'Error de Drive: ' . $e->getMessage(),
                ], 502);
            } catch (RuntimeException $e) {
                Log::error('saveResults drive failure', [
                    'error'                 => $e->getMessage(),
                    'transcription_folder'  => $transcriptionFolderId,
                    'audio_folder'          => $audioFolderId,
                    'service_account_email' => $accountEmail,
                ]);
                return $respondWithCleanup([
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

                // Increment monthly usage (no decrement when deleted)
                \App\Models\MonthlyMeetingUsage::incrementUsage(
                    Auth::user()->id,
                    Auth::user()->current_organization_id,
                    [
                        'meeting_id' => $meeting->id,
                        'meeting_name' => $meetingName,
                        'type' => 'uploaded'
                    ]
                );

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
                return $respondWithCleanup([
                    'message' => 'Error de base de datos: ' . $e->getMessage(),
                ], 500);
            }

            return $respondWithCleanup([
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
            return $respondWithCleanup(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('saveResults failed', [
                'exception'             => $e->getMessage(),
                'transcription_folder'  => $transcriptionFolderId,
                'audio_folder'          => $audioFolderId,
                'service_account_email' => $accountEmail,
            ]);

            if (str_contains($e->getMessage(), 'unauthorized_client')) {
                return $respondWithCleanup([
                    'message' => 'La cuenta de servicio no está autorizada para acceder a Google Drive'
                ], 403);
            }

            if (
                str_contains($e->getMessage(), 'File not found') ||
                str_contains($e->getMessage(), 'The caller does not have permission')
            ) {
                return $respondWithCleanup([
                    'code'    => 'FOLDER_NOT_SHARED',
                    'message' => "La carpeta no está compartida con la cuenta de servicio. Comparte la carpeta con {$accountEmail}",
                ], 403);
            }

            return $respondWithCleanup(['message' => 'Error interno'], 500);
        }
    }

    private function getChunkedAudioBasePath(string $uploadId): string
    {
        return storage_path('app/temp-save-audio/' . $uploadId);
    }

    private function getChunkedAudioMetadataPath(string $uploadId): string
    {
        return $this->getChunkedAudioBasePath($uploadId) . '/metadata.json';
    }

    private function updateChunkedAudioMetadata(string $metadataPath, callable $callback): void
    {
        $dir = dirname($metadataPath);
        if (!is_dir($dir)) {
            return;
        }

        $fp = fopen($metadataPath, 'c+');
        if (!$fp) {
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                return;
            }

            $raw = stream_get_contents($fp);
            $metadata = $raw ? json_decode($raw, true) : [];
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $callback($metadata);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($metadata));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    private function resolveChunkedAudioUpload(?string $uploadId): ?array
    {
        if (!$uploadId) {
            return null;
        }

        $metadataPath = $this->getChunkedAudioMetadataPath($uploadId);
        if (!file_exists($metadataPath)) {
            return null;
        }

        $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
        $finalPath = $metadata['final_path'] ?? null;
        if (!$finalPath || !file_exists($finalPath)) {
            return null;
        }

        $mime = strtolower($metadata['mime_type'] ?? '');
        if (!$mime && !empty($metadata['filename'])) {
            $ext = strtolower(pathinfo($metadata['filename'], PATHINFO_EXTENSION));
            $mimeMap = [
                'mp3' => 'audio/mpeg',
                'm4a' => 'audio/mp4',
                'mp4' => 'video/mp4',
                'aac' => 'audio/aac',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'oga' => 'audio/ogg',
                'opus' => 'audio/opus',
                'webm' => 'audio/webm',
                'flac' => 'audio/flac',
                'amr' => 'audio/amr',
                '3gp' => 'audio/3gpp',
                '3g2' => 'audio/3gpp2',
            ];
            $mime = $mimeMap[$ext] ?? '';
        }

        return [
            'metadata_path' => $metadataPath,
            'directory' => dirname($metadataPath),
            'path' => $finalPath,
            'mime' => $mime,
        ];
    }

    private function cleanupChunkedAudioUpload(?string $uploadId): void
    {
        if (!$uploadId) {
            return;
        }

        $baseDir = $this->getChunkedAudioBasePath($uploadId);
        if (!is_dir($baseDir)) {
            return;
        }

        try {
            $files = glob($baseDir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($baseDir);
        } catch (\Throwable $e) {
            Log::warning('cleanupChunkedAudioUpload failed', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function storeTemporaryResult(
        User $user,
        array $validated,
        Request $request,
        callable $respondWithCleanup,
        callable $cleanupChunkedUpload,
        array $mimeToExt,
        int $maxAudioBytes,
        array $context = []
    ) {
        try {
            $audioUploadId = $validated['audioUploadId'] ?? null;
            $audioFile = $request->file('audioFile');
            $audioMime = strtolower((string)($validated['audioMimeType'] ?? ''));
            $tmp = null;
            $storageReason = $context['reason'] ?? null;
            $driveTypeContext = $context['drive_type'] ?? ($validated['driveType'] ?? 'personal');

            if ($audioUploadId) {
                $resolvedUpload = $this->resolveChunkedAudioUpload($audioUploadId);
                if (!$resolvedUpload) {
                    return $respondWithCleanup([
                        'message' => 'El archivo de audio temporal no está disponible o expiró. Vuelve a subir el audio e inténtalo de nuevo.'
                    ], 422);
                }

                $sourcePath = $resolvedUpload['path'];
                if (filesize($sourcePath) > $maxAudioBytes) {
                    return $respondWithCleanup([
                        'message' => 'Archivo de audio demasiado grande (máx. 200 MB)'
                    ], 422);
                }

                $tmp = tempnam(sys_get_temp_dir(), 'aud');
                if (!@copy($sourcePath, $tmp)) {
                    return $respondWithCleanup([
                        'message' => 'No se pudo preparar el audio temporal'
                    ], 500);
                }
                $audioMime = $resolvedUpload['mime'] ?: $audioMime;
            } elseif ($audioFile instanceof UploadedFile) {
                if ($audioFile->getSize() > $maxAudioBytes) {
                    return $respondWithCleanup([
                        'message' => 'Archivo de audio demasiado grande (máx. 200 MB)'
                    ], 422);
                }

                $tmp = tempnam(sys_get_temp_dir(), 'aud');
                file_put_contents($tmp, file_get_contents($audioFile->getRealPath()));
                $audioMime = strtolower($audioFile->getMimeType() ?: $audioFile->getClientMimeType() ?: $audioMime);
            } else {
                $b64 = $validated['audioData'] ?? '';
                if (str_contains($b64, ',')) {
                    [, $b64] = explode(',', $b64, 2);
                }
                $raw = base64_decode($b64);
                if ($raw === false) {
                    return $respondWithCleanup([
                        'message' => 'Audio inválido o corrupto'
                    ], 422);
                }
                if (strlen($raw) > $maxAudioBytes) {
                    return $respondWithCleanup([
                        'message' => 'Archivo de audio demasiado grande (máx. 200 MB)'
                    ], 422);
                }

                $tmp = tempnam(sys_get_temp_dir(), 'aud');
                file_put_contents($tmp, $raw);
            }

            if (!$tmp) {
                return $respondWithCleanup([
                    'message' => 'No se pudo procesar el audio temporal'
                ], 422);
            }

            $normalizedMime = $audioMime ?: 'audio/ogg';
            $extension = $mimeToExt[$normalizedMime] ?? null;
            if (!$extension && $audioFile instanceof UploadedFile) {
                $extension = strtolower($audioFile->getClientOriginalExtension());
            }
            if (!$extension || strlen($extension) > 6) {
                $extension = 'ogg';
            }

            $baseName = Str::slug($validated['meetingName'] ?? 'reunion');
            if (!$baseName) {
                $baseName = 'reunion';
            }
            $baseName = Str::limit($baseName, 60, '');
            $audioFileName = $baseName . '_' . uniqid() . '.' . $extension;

            $audioDirectory = 'temp_audio/' . $user->id;
            Storage::disk('local')->makeDirectory($audioDirectory);
            $audioStoragePath = $audioDirectory . '/' . $audioFileName;
            Storage::disk('local')->put($audioStoragePath, file_get_contents($tmp));
            $audioSize = Storage::disk('local')->size($audioStoragePath);

            $analysis = is_array($validated['analysisResults']) ? $validated['analysisResults'] : [];
            $transcriptionData = $validated['transcriptionData'];
            $payload = [
                'segments' => $transcriptionData,
                'summary' => $analysis['summary'] ?? null,
                'keyPoints' => $analysis['keyPoints'] ?? [],
            ];
            $encrypted = Crypt::encryptString(json_encode($payload));

            $juDirectory = 'temp_transcriptions/' . $user->id;
            Storage::disk('local')->makeDirectory($juDirectory);
            $juFileName = $baseName . '_' . uniqid() . '.ju';
            $juStoragePath = $juDirectory . '/' . $juFileName;
            Storage::disk('local')->put($juStoragePath, $encrypted);

            $duration = 0;
            $speakers = [];
            foreach ($transcriptionData as $segment) {
                if (isset($segment['end']) && $segment['end'] > $duration) {
                    $duration = $segment['end'];
                }
                if (!empty($segment['speaker'])) {
                    $speakers[$segment['speaker']] = true;
                }
            }
            $speakerCount = count($speakers);

            $retentionDays = app(PlanLimitService::class)->getTemporaryRetentionDays($user);
            $expiresAt = Carbon::now()->addDays($retentionDays);

            $tempMeeting = TranscriptionTemp::create([
                'user_id' => $user->id,
                'title' => $validated['meetingName'],
                'description' => $analysis['summary'] ?? null,
                'audio_path' => $audioStoragePath,
                'transcription_path' => $juStoragePath,
                'audio_size' => $audioSize,
                'duration' => $duration,
                'expires_at' => $expiresAt,
                'metadata' => [
                    'analysis' => $analysis,
                    'segments_count' => count($transcriptionData),
                    'speaker_count' => $speakerCount,
                    'storage_type' => 'temp',
                    'retention_days' => $retentionDays,
                    'audio_mime' => $normalizedMime,
                    'transcription_segments' => $transcriptionData,
                    'key_points' => $analysis['keyPoints'] ?? [],
                    'summary' => $analysis['summary'] ?? null,
                    'storage_reason' => $storageReason,
                    'drive_type' => $driveTypeContext,
                ],
            ]);

            $createdTaskModels = [];
            if (!empty($analysis['tasks']) && is_array($analysis['tasks'])) {
                foreach ($analysis['tasks'] as $rawTask) {
                    $taskInfo = $this->parseRawTaskForDb($rawTask);

                    if (empty($taskInfo['tarea'])) {
                        continue;
                    }

                    $createdTaskModels[] = TaskLaravel::create([
                        'username' => $user->username,
                        'meeting_id' => $tempMeeting->id,
                        'meeting_type' => 'temporary',
                        'tarea' => $taskInfo['tarea'],
                        'descripcion' => $taskInfo['descripcion'],
                        'prioridad' => $taskInfo['prioridad'] ?? 'media',
                        'asignado' => $taskInfo['asignado'] ?? null,
                        'fecha_inicio' => $taskInfo['fecha_inicio'] ?? null,
                        'fecha_limite' => $taskInfo['fecha_limite'] ?? null,
                        'hora_limite' => $taskInfo['hora_limite'] ?? null,
                        'progreso' => $taskInfo['progreso'] ?? 0,
                        'assigned_user_id' => null,
                        'assignment_status' => 'pending',
                    ]);
                }
            }

            if (!empty($createdTaskModels)) {
                $tempMeeting->tasks = array_map(static function (TaskLaravel $task) {
                    return [
                        'tarea' => $task->tarea,
                        'descripcion' => $task->descripcion,
                        'prioridad' => $task->prioridad,
                        'asignado' => $task->asignado,
                        'fecha_limite' => optional($task->fecha_limite)->toDateString(),
                        'hora_limite' => $task->hora_limite,
                        'progreso' => $task->progreso,
                    ];
                }, $createdTaskModels);
                $tempMeeting->save();
            }

            // Increment monthly usage (no decrement when deleted)
            \App\Models\MonthlyMeetingUsage::incrementUsage(
                $user->id,
                $user->current_organization_id,
                [
                    'meeting_id' => $tempMeeting->id,
                    'meeting_name' => $validated['meetingName'],
                    'type' => 'temporary_uploaded'
                ]
            );

            if ($tmp && file_exists($tmp)) {
                @unlink($tmp);
            }

            return $respondWithCleanup([
                'saved' => true,
                'storage' => 'temp',
                'storage_reason' => $storageReason,
                'storage_path' => $audioStoragePath,
                'drive_type' => $driveTypeContext,
                'drive_path' => 'Almacenamiento temporal',
                'audio_duration' => $duration,
                'speaker_count' => $speakerCount,
                'expires_at' => optional($tempMeeting->expires_at)->toIso8601String(),
                'time_remaining' => $tempMeeting->time_remaining,
                'retention_days' => $retentionDays,
                'temp_meeting_id' => $tempMeeting->id,
                'tasks' => array_map(static function (TaskLaravel $task) {
                    return [
                        'id' => $task->id,
                        'tarea' => $task->tarea,
                        'descripcion' => $task->descripcion,
                        'prioridad' => $task->prioridad,
                        'asignado' => $task->asignado,
                        'fecha_limite' => optional($task->fecha_limite)->toDateString(),
                        'hora_limite' => $task->hora_limite,
                        'progreso' => $task->progreso,
                    ];
                }, $createdTaskModels),
            ]);
        } catch (\Throwable $e) {
            Log::error('storeTemporaryResult failed', [
                'error' => $e->getMessage(),
                'user' => $user->username,
            ]);

            return $respondWithCleanup([
                'message' => 'Error al guardar la reunión temporal'
            ], 500);
        }
    }
}
