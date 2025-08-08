<?php
namespace App\Http\Controllers;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Services\GoogleDriveService;
use App\Services\GoogleServiceAccount;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\TranscriptionLaravel;
use App\Models\PendingRecording;
use Illuminate\Support\Facades\Log;
use Google\Service\Drive as DriveService;
use Google\Service\Exception as GoogleServiceException;
use App\Http\Controllers\Auth\GoogleAuthController;

class DriveController extends Controller
{
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
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);
        if ($client->isAccessTokenExpired()) {
            $client->refreshToken($token->refresh_token);
        }

        try {
            $folderId = $this->drive->createFolder(
                $request->input('name'),
                config('drive.root_folder_id')
            );
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
            $client->refreshToken($token->refresh_token);
        }

        $drive      = $this->drive->getDrive();
        $folderData = $drive->files->get($request->input('id'), ['fields' => 'name']);
        $folderName = $folderData->getName();

        $token->recordings_folder_id = $request->input('id');
        $token->save();

        Folder::updateOrCreate(
            [
                'google_token_id' => $token->id,
                'google_id'       => $request->input('id'),
            ],
            [
                'name'      => $folderName,
                'parent_id' => null,
            ]
        );

        $this->drive->shareFolder(
            $request->input('id'),
            config('services.google.service_account_email')
        );

        return response()->json([
            'id'   => $request->input('id'),
            'name' => $folderName,
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
            $client->refreshToken($token->refresh_token);
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
            $client->refreshToken($token->refresh_token);
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

            // 4. Crear o recuperar la carpeta raíz en BD
            $rootFolder = Folder::firstOrCreate(
                [
                    'google_token_id' => $token->id,
                    'google_id'       => $token->recordings_folder_id,
                ],
                [
                    'name'      => "recordings_{$username}",
                    'parent_id' => null,
                ]
            );

            // 5. Sincronizar subcarpetas en BD
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

            // 6. Devolver JSON con el resultado
            return response()->json([
                'root_folder' => $rootFolder,
                'subfolders'  => $subfolders,
            ], 200);

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
            $client->refreshToken($token->refresh_token);
        }

        $this->drive->deleteFile($id);

        Subfolder::where('google_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function uploadPendingAudio(Request $request)
    {
        try {
            $v = $request->validate([
                'meetingName' => 'required|string',
                'audioFile'   => 'required|file|mimetypes:audio/mpeg,audio/mp3,audio/webm,audio/ogg,audio/wav,audio/x-wav,audio/wave,audio/mp4',
            ]);

            $serviceAccount   = app(GoogleServiceAccount::class);
            $pendingFolderId  = config('services.google.pending_folder_id')
                ?: $serviceAccount->getOrCreatePendingFolder(Auth::user());
            $file             = $request->file('audioFile');
            $mime             = $file->getMimeType() ?? 'application/octet-stream';
            $extension        = $file->getClientOriginalExtension();
            $fileName         = $v['meetingName'] . ($extension ? ('.' . $extension) : '');

            $fileId = $serviceAccount->uploadFile(
                $fileName,
                $mime,
                $pendingFolderId,
                $file->getRealPath()
            );

            $pending = PendingRecording::create([
                'user_id'        => Auth::id(),
                'meeting_name'   => $v['meetingName'],
                'audio_drive_id' => $fileId,
                'status'         => PendingRecording::STATUS_PENDING,
            ]);

            return response()->json([
                'id'                  => $fileId,
                'pending_recording'   => $pending->id,
                'status'              => $pending->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('uploadPendingAudio failed', [
                'error' => $e->getMessage(),
                'user'  => Auth::user()?->id,
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
        $pendingFolderId = $serviceAccount->getOrCreatePendingFolder(Auth::user());

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


        // Permitir que rootFolder sea id interno o google_id
        $rootFolder = Folder::where(function($q) use ($v) {
            $q->where('google_id', $v['rootFolder'])
              ->orWhere('id', $v['rootFolder']);
        })->first();
        if (!$rootFolder) {
            return response()->json(['message' => 'Carpeta principal no encontrada en la base de datos'], 400);
        }
        // Si hay subcarpeta, obtener el ID real, si no, usar el de la raíz

        // Permitir que los IDs de subcarpeta sean tanto google_id como id interno
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
                'tasks'     => $analysis['tasks']     ?? [],
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
            $rootName = Folder::where('google_id', $v['rootFolder'])->value('name');
            $drivePath = $rootName ?? '';

            $subfolderId = $v['transcriptionSubfolder'] ?: $v['audioSubfolder'];
            if ($subfolderId) {
                $subName = Subfolder::where('google_id', $subfolderId)->value('name');
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
            $tasks        = $analysis['tasks'] ?? [];

            // 8. Guarda en BD y responde
            try {
                TranscriptionLaravel::create([
                    'username'               => Auth::user()->username,
                    'meeting_name'           => $meetingName,
                    'audio_drive_id'         => $audioFileId,
                    'audio_download_url'     => $audioUrl,
                    'transcript_drive_id'    => $transcriptFileId,
                    'transcript_download_url' => $transcriptUrl,
                ]);
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
                'tasks'                   => $tasks,
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
