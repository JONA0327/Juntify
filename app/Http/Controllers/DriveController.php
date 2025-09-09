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

class DriveController extends Controller
{
    use MeetingContentParsing;

    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
        Log::info('DriveController constructor called', ['user' => Auth::user() ? Auth::user()->username : null]);
    }

    public function createMainFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
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

        try {
            $folderId = $this->drive->createFolder(
                $request->input('name'),
                config('drive.root_folder_id')
            );
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

            Log::error('createMainFolder Google error', [
                'username' => Auth::user()->username,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error de Drive: ' . $e->getMessage(),
            ], 502);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        GoogleToken::where('username', Auth::user()->username)
            ->update(['recordings_folder_id' => $folderId]);

        $folder = Folder::create([
            'google_token_id' => $token->id,
            'google_id'       => $folderId,
            'name'            => $request->input('name'),
            'parent_id'       => null,
        ]);

        $this->drive->shareFolder($folderId, config('services.google.service_account_email'));

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
        $folderData = $drive->files->get($request->input('id'), ['fields' => 'name']);
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

        return response()->json([
            'id'         => $request->input('id'),
            'name'       => $folderName,
            'subfolders' => $subfolders,
        ]);
    }

    public function createSubfolder(Request $request)
    {
        $token  = GoogleToken::where('username', Auth::user()->username)->firstOrFail();
        $parentId = $token->recordings_folder_id;

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

        $folderId = $this->drive->createFolder($request->input('name'), $parentId);

        if ($folder = Folder::where('google_id', $parentId)->first()) {
            Subfolder::create([
                'folder_id' => $folder->id,
                'google_id' => $folderId,
                'name'      => $request->input('name'),
            ]);
        }

        $this->drive->shareFolder(
            $folderId,
            config('services.google.service_account_email')
        );

        return response()->json(['id' => $folderId]);
    }

    public function syncDriveSubfolders(Request $request)
    {
        // 1. Obtener el GoogleToken del usuario autenticado
        $username = Auth::user()->username;
        $token    = GoogleToken::where('username', $username)->firstOrFail();

        if (empty($token->recordings_folder_id)) {
            return response()->json([
                'message' => 'El usuario no tiene configurada la carpeta principal'
            ], 400);
        }

        // 2. Crear cliente de Drive usando el método protegido createClient()
        $authController = app(GoogleAuthController::class);
        $refMethod      = new \ReflectionMethod($authController, 'createClient');
        $refMethod->setAccessible(true);
        /** @var \Google\Client $client */
        $client = $refMethod->invoke($authController);
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);
        // Auto-refrescar si ya expiró
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

        $drive = new DriveService($client);

        try {
            // 3. Listar subcarpetas con la query correcta (comillas simples)
            $query = sprintf(
                "mimeType='application/vnd.google-apps.folder' and '%s' in parents and trashed=false",
                $token->recordings_folder_id
            );

            $response = $drive->files->listFiles([
                'q'      => $query,
                'fields' => 'files(id,name)',
            ]);

            $files = $response->getFiles();

            // 4. Obtener nombre real de la carpeta raíz
            $rootName = null;
            try {
                $rootData = $drive->files->get(
                    $token->recordings_folder_id,
                    ['fields' => 'name']
                );
                $rootName = $rootData->getName();
            } catch (\Throwable $e) {
                Log::warning('syncDriveSubfolders root name fetch failed', [
                    'username'  => $username,
                    'folder_id' => $token->recordings_folder_id,
                    'error'     => $e->getMessage(),
                ]);
            }

            // 5. Crear o actualizar la carpeta raíz en BD con el nombre obtenido
            $rootFolder = Folder::updateOrCreate(
                [
                    'google_token_id' => $token->id,
                    'google_id'       => $token->recordings_folder_id,
                ],
                [
                    'name'      => $rootName ?? "recordings_{$username}",
                    'parent_id' => null,
                ]
            );

            // 6. Sincronizar subcarpetas en BD
            $subfolders = [];
            foreach ($files as $file) {
                $subfolders[] = Subfolder::updateOrCreate(
                    [
                        'folder_id' => $rootFolder->id,
                        'google_id' => $file->getId(),
                    ],
                    [
                        'name' => $file->getName(),
                    ]
                );
            }

            // 7. Devolver JSON con el resultado
            return response()->json([
                'root_folder' => $rootFolder,
                'subfolders'  => $subfolders,
            ], 200);

        } catch (GoogleServiceException $e) {
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                Log::warning('syncDriveSubfolders invalid_grant', [
                    'username' => $username,
                ]);
                GoogleToken::where('username', $username)->update([
                    'access_token'  => null,
                    'refresh_token' => null,
                    'expiry_date'   => null,
                    // recordings_folder_id se mantiene para reconexión
                ]);
                return response()->json([
                    'message' => 'Autenticación de Google expirada. Reconecta tu Google Drive.',
                ], 401);
            }

            Log::error('syncDriveSubfolders Google error', [
                'username' => $username,
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error de Drive: ' . $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error('syncDriveSubfolders failed', [
                'username' => $username,
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error interno al obtener subcarpetas'
            ], 500);
        }
    }
    public function status()
    {
        $connected = GoogleToken::where('username', Auth::user()->username)->exists();

        return response()->json(['connected' => $connected]);
    }

    public function deleteSubfolder(string $id)
    {
        $token  = GoogleToken::where('username', Auth::user()->username)->firstOrFail();
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

        $this->drive->deleteFile($id);

        Subfolder::where('google_id', $id)->delete();

        return response()->json(['deleted' => true]);
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
        $maxAudioBytes = 100 * 1024 * 1024; // 100 MB
        try {
            $v = $request->validate([
                'meetingName' => 'required|string',
                'audioFile'   => 'required|file|mimetypes:audio/mpeg,audio/mp3,audio/webm,video/webm,audio/ogg,audio/wav,audio/x-wav,audio/wave,audio/mp4,video/mp4',
                'rootFolder'  => 'nullable|string', // Cambiar a nullable
                'driveType'   => 'nullable|string|in:personal,organization', // Nuevo campo para tipo de drive
            ]);

            $user = Auth::user();
            $serviceAccount = app(GoogleServiceAccount::class);

            $organizationFolder = $user->organizationFolder;
            $orgRole = $user->organizations()
                ->where('organization_id', $user->current_organization_id)
                ->first()?->pivot->rol;

            // Determinar si usar Drive organizacional basado en driveType
            $driveType = $v['driveType'] ?? 'personal'; // Default a personal si no se especifica
            $useOrgDrive = false;

            Log::info('uploadPendingAudio: Drive type selection', [
                'driveType' => $driveType,
                'hasOrganizationFolder' => !!$organizationFolder,
                'orgRole' => $orgRole,
                'username' => $user->username
            ]);

            if ($driveType === 'organization' && $organizationFolder) {
                if ($orgRole === 'colaborador' || $orgRole === 'administrador') {
                    $useOrgDrive = true;
                    Log::info('uploadPendingAudio: Using organization drive', [
                        'orgRole' => $orgRole,
                        'orgFolderId' => $organizationFolder->google_id
                    ]);
                } else {
                    Log::warning('uploadPendingAudio: User has no valid role for organization', [
                        'orgRole' => $orgRole,
                        'username' => $user->username
                    ]);
                }
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

                        $folderId = $driveService->createFolder($defaultFolderName);

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
                            'folder_name' => $defaultFolderName
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

            // 3. Buscar o crear subcarpeta 'Audios Pospuestos' en la raíz
            $pendingSubfolderName = 'Audios Pospuestos';
            if ($useOrgDrive) {
                $subfolder = OrganizationSubfolder::where('organization_folder_id', $rootFolder->id)
                    ->where('name', $pendingSubfolderName)
                    ->first();
            } else {
                $subfolder = Subfolder::where('folder_id', $rootFolder->id)
                    ->where('name', $pendingSubfolderName)
                    ->first();
            }
            if ($subfolder) {
                $pendingFolderId = $subfolder->google_id;
                $subfolderCreated = false;
            } else {
                Log::info('uploadPendingAudio: creating Audios Pospuestos subfolder', ['name' => $pendingSubfolderName, 'rootFolderId' => $rootFolderId]);
                $pendingFolderId = $serviceAccount->createFolder($pendingSubfolderName, $rootFolderId);
                if ($useOrgDrive) {
                    $subfolder = OrganizationSubfolder::create([
                        'organization_folder_id' => $rootFolder->id,
                        'google_id'              => $pendingFolderId,
                        'name'                   => $pendingSubfolderName,
                    ]);
                } else {
                    $subfolder = Subfolder::create([
                        'folder_id' => $rootFolder->id,
                        'google_id' => $pendingFolderId,
                        'name'      => $pendingSubfolderName,
                    ]);
                }
                $subfolderCreated = true;
            }

            // Nota: Si la cuenta de servicio no tiene acceso de escritura al folder raíz,
            // la creación/subida fallará con 403/404. Pediremos que compartan con la SA.

            // 3. Subir el audio a la subcarpeta
            $file = $request->file('audioFile');
            if ($file->getSize() > $maxAudioBytes) {
                return response()->json(['message' => 'Archivo de audio demasiado grande (máx. 100 MB)'], 413);
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
            $fileName = $v['meetingName'] . '.' . $ext;

            Log::debug('uploadPendingAudio uploading to Drive', [
                'fileName' => $fileName,
                'mime' => $mime,
                'pendingFolderId' => $pendingFolderId,
                'filePath' => $filePath,
            ]);
            $fileContents = file_get_contents($filePath);
            $fileId = $serviceAccount->uploadFile(
                $fileName,
                $mime,
                $pendingFolderId,
                $fileContents
            );
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
            if ($subfolderCreated) {
                \App\Models\PendingFolder::firstOrCreate([
                    'username' => $user->username,
                    'google_id' => $subfolder->google_id,
                ], [
                    'name' => $subfolder->name,
                ]);
            }
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
        $mimeToExt = [
            'audio/mpeg' => 'mp3',
            'audio/mp3'  => 'mp3',
            'audio/webm' => 'webm',
            'audio/ogg'  => 'ogg',
            'audio/wav'  => 'wav',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/mp4'  => 'mp4',
        ];
        $baseMime = explode(';', $mime)[0];
        $ext = $mimeToExt[$baseMime] ?? preg_replace('/[^\w]/', '', explode('/', $baseMime, 2)[1] ?? '');

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
        $maxAudioBytes = 100 * 1024 * 1024; // 100 MB

        $v = $request->validate([
            'meetingName'            => 'required|string',
            'rootFolder'             => 'required|string',
            'transcriptionSubfolder' => 'nullable|string',
            'audioSubfolder'         => 'nullable|string',
            'transcriptionData'      => 'required',
            'analysisResults'        => 'required',
            'audioData'              => 'required|string|max:' . (int) ceil($maxAudioBytes * 4 / 3),      // Base64 (~133MB)
            'audioMimeType'          => 'required|string',      // p.ej. "audio/webm"
        ], [
            'audioData.max' => 'Archivo de audio demasiado grande (máx. 100 MB)',
        ]);


        $user = Auth::user();
        $organizationFolder = $user->organizationFolder;
        $orgRole = $user->organizations()
            ->where('organization_id', $user->current_organization_id)
            ->first()?->pivot->rol;

        $useOrgDrive = false;
        if ($organizationFolder) {
            if ($orgRole === 'colaborador') {
                if ($v['rootFolder'] !== $organizationFolder->google_id) {
                    return response()->json([
                        'message' => 'Colaboradores solo pueden usar la carpeta de la organización'
                    ], 403);
                }
                $useOrgDrive = true;
            } elseif ($orgRole === 'administrador' && $v['rootFolder'] === $organizationFolder->google_id) {
                $useOrgDrive = true;
            }
        }

        if ($useOrgDrive) {
            $rootFolder = $organizationFolder;
            $transcriptionFolderId = $rootFolder->google_id;
            if ($v['transcriptionSubfolder']) {
                $sub = OrganizationSubfolder::where(function($q) use ($v) {
                    $q->where('google_id', $v['transcriptionSubfolder'])
                      ->orWhere('id', $v['transcriptionSubfolder']);
                })->first();
                if ($sub) {
                    $transcriptionFolderId = $sub->google_id;
                } else {
                    return response()->json(['message' => 'ID de carpeta o subcarpeta inválido'], 400);
                }
            }
            $audioFolderId = $rootFolder->google_id;
            if ($v['audioSubfolder']) {
                $sub = OrganizationSubfolder::where(function($q) use ($v) {
                    $q->where('google_id', $v['audioSubfolder'])
                      ->orWhere('id', $v['audioSubfolder']);
                })->first();
                if ($sub) {
                    $audioFolderId = $sub->google_id;
                } else {
                    return response()->json(['message' => 'ID de carpeta o subcarpeta inválido'], 400);
                }
            }
        } else {
            // Personal drive
            $token = GoogleToken::where('username', $user->username)->first();
            if (! $token) {
                return response()->json(['message' => 'Token de Google no encontrado'], 400);
            }

            $rootFolder = Folder::where('google_token_id', $token->id)
                ->where(function($q) use ($v) {
                    $q->where('google_id', $v['rootFolder'])
                      ->orWhere('id', $v['rootFolder']);
                })
                ->first();
            if (!$rootFolder) {
                return response()->json(['message' => 'Carpeta principal no encontrada en la base de datos'], 400);
            }

            $transcriptionFolderId = $rootFolder->google_id;
            if ($v['transcriptionSubfolder']) {
                $sub = Subfolder::where(function($q) use ($v) {
                    $q->where('google_id', $v['transcriptionSubfolder'])
                      ->orWhere('id', $v['transcriptionSubfolder']);
                })->first();
                if ($sub) {
                    $transcriptionFolderId = $sub->google_id;
                } else {
                    return response()->json(['message' => 'ID de carpeta o subcarpeta inválido'], 400);
                }
            }
            $audioFolderId = $rootFolder->google_id;
            if ($v['audioSubfolder']) {
                $sub = Subfolder::where(function($q) use ($v) {
                    $q->where('google_id', $v['audioSubfolder'])
                      ->orWhere('id', $v['audioSubfolder']);
                })->first();
                if ($sub) {
                    $audioFolderId = $sub->google_id;
                } else {
                    return response()->json(['message' => 'ID de carpeta o subcarpeta inválido'], 400);
                }
            }
        }

        $accountEmail = config('services.google.service_account_email');
        $serviceAccount = app(GoogleServiceAccount::class);

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

            // 3. Decodifica Base64
            $b64    = $v['audioData'];
            if (str_contains($b64, ',')) {
                [, $b64] = explode(',', $b64, 2);
            }
            $raw    = base64_decode($b64);

            // 4. Guarda temporalmente el binario (NO lo cargues en memoria)
            $tmp   = tempnam(sys_get_temp_dir(), 'aud');
            file_put_contents($tmp, $raw);
            // $audio = file_get_contents($tmp); // NO cargar en memoria

            // 5. Prepara payload de transcripción/análisis
            $analysis = $v['analysisResults'];
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
                $mime = strtolower($v['audioMimeType']);
                $mimeToExt = [
                    'audio/mpeg' => 'mp3',
                    'audio/mp3'  => 'mp3',
                    'audio/webm' => 'webm',
                    'audio/ogg'  => 'ogg',
                    'audio/wav'  => 'wav',
                    'audio/x-wav' => 'wav',
                    'audio/wave' => 'wav',
                    'audio/mp4'  => 'mp4',
                ];
                $baseMime = explode(';', $mime)[0];
                $ext      = $mimeToExt[$baseMime]
                    ?? preg_replace('/[^\\w]/', '', explode('/', $baseMime, 2)[1] ?? '');

                $audioFileId = $serviceAccount
                    ->uploadFile("{$meetingName}.{$ext}", $v['audioMimeType'], $audioFolderId, $tmp);
                @unlink($tmp);
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

            $subfolderId = $v['transcriptionSubfolder'] ?: $v['audioSubfolder'];
            if ($subfolderId) {
                $subName = $useOrgDrive
                    ? OrganizationSubfolder::where('google_id', $subfolderId)->value('name')
                    : Subfolder::where('google_id', $subfolderId)->value('name');
                if ($subName) {
                    $drivePath .= "/{$subName}";
                }
            }

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
