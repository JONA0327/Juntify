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
use Google\Service\Exception as GoogleServiceException;
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

    /**
     * Ensure standard meeting subfolders (Audio/Transcripciones) exist for the provided root folder.
     *
     * @param  Folder|OrganizationFolder  $rootFolder
     * @param  GoogleServiceAccount|null  $serviceAccount
     * @param  iterable|null  $knownSubfolders
     * @return array{
     *     audio: array{name:string,google_id:string,model:mixed,path:string},
     *     transcriptions: array{name:string,google_id:string,model:mixed,path:string},
     *     root: Folder|OrganizationFolder
     * }
     */
    public static function ensureStandardMeetingFolders(
        Folder|OrganizationFolder $rootFolder,
        ?GoogleServiceAccount $serviceAccount = null,
        ?iterable $knownSubfolders = null
    ): array {
        $serviceAccount ??= app(GoogleServiceAccount::class);
        $serviceEmail = config('services.google.service_account_email');
        $isOrg = $rootFolder instanceof OrganizationFolder;
        $modelClass = $isOrg ? OrganizationSubfolder::class : Subfolder::class;
        $foreignKey = $isOrg ? 'organization_folder_id' : 'folder_id';

        if ($knownSubfolders === null) {
            $knownSubfolders = $modelClass::where($foreignKey, $rootFolder->id)->get();
        }

        $existing = [];
        foreach ($knownSubfolders as $subfolder) {
            $name = is_array($subfolder) ? ($subfolder['name'] ?? null) : ($subfolder->name ?? null);
            if (! $name) {
                continue;
            }
            $key = mb_strtolower($name);
            $existing[$key] = [
                'id'    => is_array($subfolder) ? ($subfolder['google_id'] ?? null) : ($subfolder->google_id ?? null),
                'model' => $subfolder instanceof \Illuminate\Database\Eloquent\Model ? $subfolder : null,
            ];
        }

        try {
            $serviceAccount->shareFolder($rootFolder->google_id, $serviceEmail);
        } catch (\Throwable $e) {
            if (! str_contains(strtolower($e->getMessage()), 'already')) {
                Log::debug('ensureStandardMeetingFolders root share failed', [
                    'root'  => $rootFolder->google_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $results = [
            'root' => $rootFolder,
        ];
        $standardMap = [
            'transcriptions' => 'Transcripciones',
            'audio'          => 'Audio',
        ];

        foreach ($standardMap as $key => $label) {
            $lookup = mb_strtolower($label);
            $record = $existing[$lookup]['model'] ?? null;
            $folderId = $existing[$lookup]['id'] ?? null;

            if ($record instanceof \Illuminate\Database\Eloquent\Model && $record->google_id) {
                $folderId = $record->google_id;
            }

            if (! $folderId) {
                try {
                    $folderId = $serviceAccount->createFolder($label, $rootFolder->google_id);
                } catch (\Throwable $e) {
                    Log::error('ensureStandardMeetingFolders create failed', [
                        'root'  => $rootFolder->google_id,
                        'name'  => $label,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $record = $modelClass::updateOrCreate(
                [
                    $foreignKey => $rootFolder->id,
                    'name'      => $label,
                ],
                [
                    'google_id' => $folderId,
                ]
            );

            try {
                $serviceAccount->shareFolder($folderId, $serviceEmail);
            } catch (\Throwable $e) {
                if (! str_contains(strtolower($e->getMessage()), 'already')) {
                    Log::debug('ensureStandardMeetingFolders share failed', [
                        'folder' => $folderId,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            $results[$key] = [
                'name'      => $label,
                'google_id' => $folderId,
                'model'     => $record,
                'path'      => trim(($rootFolder->name ? $rootFolder->name . '/' : '') . $label, '/'),
            ];
        }

        return $results;
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

        $standardFolders = self::ensureStandardMeetingFolders($folder, null, $subfolders);
        $subfolderCollection = collect($subfolders);
        if (! $subfolderCollection->contains(fn ($item) => $item->id === $standardFolders['audio']['model']->id)) {
            $subfolders[] = $standardFolders['audio']['model'];
        }
        if (! $subfolderCollection->contains(fn ($item) => $item->id === $standardFolders['transcriptions']['model']->id)) {
            $subfolders[] = $standardFolders['transcriptions']['model'];
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
            'id'                  => $request->input('id'),
            'name'                => $folderName,
            'subfolders'          => $subfolders,
            'standard_subfolders' => [
                'audio'          => [
                    'id'        => $standardFolders['audio']['model']->id,
                    'google_id' => $standardFolders['audio']['google_id'],
                    'name'      => $standardFolders['audio']['name'],
                    'path'      => $standardFolders['audio']['path'],
                ],
                'transcriptions' => [
                    'id'        => $standardFolders['transcriptions']['model']->id,
                    'google_id' => $standardFolders['transcriptions']['google_id'],
                    'name'      => $standardFolders['transcriptions']['name'],
                    'path'      => $standardFolders['transcriptions']['path'],
                ],
            ],
        ]);
    }


    public function status()
    {
        $user = Auth::user();
        $token = GoogleToken::where('username', $user->username)->first();

        $connected = false;
        $rootFolder = null;
        $standardFolders = collect();

        if ($token) {
            $connected = $token->hasValidAccessToken() && ! empty($token->refresh_token);
            $rootFolder = Folder::where('google_token_id', $token->id)->first();

            if ($connected && $rootFolder) {
                try {
                    $results = self::ensureStandardMeetingFolders($rootFolder);
                    $standardFolders = collect([
                        $results['audio'] ?? null,
                        $results['transcriptions'] ?? null,
                    ])->filter()->map(fn ($item) => [
                        'name'      => $item['name'],
                        'google_id' => $item['google_id'],
                        'path'      => $item['path'],
                    ])->values();
                } catch (\Throwable $e) {
                    Log::warning('drive.status ensureStandardMeetingFolders failed', [
                        'user'  => $user->username,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'connected'          => $connected,
            'root_folder'        => $rootFolder,
            'standard_subfolders'=> $standardFolders,
        ]);
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
            'transcriptionSubfolder' => 'prohibited',
            'audioSubfolder'         => 'prohibited',
            'transcriptionData'      => 'required',
            'analysisResults'        => 'required',
            'audioData'              => 'required|string|max:' . (int) ceil($maxAudioBytes * 4 / 3),      // Base64 (~133MB)
            'audioMimeType'          => 'required|string',      // p.ej. "audio/webm"
            'driveType'              => 'nullable|string|in:personal,organization', // Nuevo campo para tipo de drive
        ], [
            'audioData.max' => 'Archivo de audio demasiado grande (máx. 100 MB)',
        ]);


        $user = Auth::user();
        $organizationFolder = $user->organizationFolder;
        $orgRole = $user->organizations()
            ->where('organization_id', $user->current_organization_id)
            ->first()?->pivot->rol;

        // Determinar si usar Drive organizacional basado en driveType
        $driveType = $v['driveType'] ?? 'personal'; // Default a personal si no se especifica
        $useOrgDrive = false;

        Log::info('saveResults: Drive type selection', [
            'driveType' => $driveType,
            'hasOrganizationFolder' => !!$organizationFolder,
            'orgRole' => $orgRole,
            'username' => $user->username,
            'rootFolder' => $v['rootFolder']
        ]);

        if ($driveType === 'organization' && $organizationFolder) {
            if ($orgRole === 'colaborador' || $orgRole === 'administrador') {
                $useOrgDrive = true;
                Log::info('saveResults: Using organization drive', [
                    'orgRole' => $orgRole,
                    'orgFolderId' => $organizationFolder->google_id
                ]);
            } else {
                Log::warning('saveResults: User has no valid role for organization', [
                    'orgRole' => $orgRole,
                    'username' => $user->username
                ]);
                return response()->json([
                    'message' => 'No tienes permisos para usar Drive organizacional'
                ], 403);
            }
        } elseif ($driveType === 'organization' && !$organizationFolder) {
            Log::warning('saveResults: Organization drive requested but no organization folder found', [
                'username' => $user->username
            ]);
            return response()->json([
                'message' => 'No tienes acceso a Drive organizacional o no está configurado'
            ], 403);
        }

        if ($useOrgDrive) {
            $rootFolder = $organizationFolder;
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
        }

        $standardFolders = self::ensureStandardMeetingFolders($rootFolder);
        $transcriptionFolderId = $standardFolders['transcriptions']['google_id'];
        $audioFolderId = $standardFolders['audio']['google_id'];
        $drivePaths = [
            'transcriptions' => $standardFolders['transcriptions']['path'],
            'audio'          => $standardFolders['audio']['path'],
        ];

        $accountEmail = config('services.google.service_account_email');
        $serviceAccount = app(GoogleServiceAccount::class);

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
            $drivePath = implode(' | ', [
                'Transcripciones: ' . $drivePaths['transcriptions'],
                'Audio: ' . $drivePaths['audio'],
            ]);

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
                'drive_paths'             => $drivePaths,
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
