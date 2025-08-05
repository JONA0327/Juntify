<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Services\GoogleDriveService;
use Carbon\Carbon;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Crypt;
use App\Models\TranscriptionLaravel;
use Illuminate\Support\Facades\Log;
use Google\Service\Drive as DriveService;
use App\Http\Controllers\Auth\GoogleAuthController;

class DriveController extends Controller
{
    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    protected function applyUserToken(): GoogleToken
    {
        $token = GoogleToken::where('username', Auth::user()->username)->firstOrFail();

        $client = $this->drive->getClient();
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in'    => max(1, Carbon::parse($token->expiry_date)->timestamp - time()),
            'created'       => time(),
        ]);

        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            }
        }

        return $token;
    }

    public function createMainFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $token = $this->applyUserToken();

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
        $this->applyUserToken();

        GoogleToken::updateOrCreate(
            ['username' => Auth::user()->username],
            ['recordings_folder_id' => $request->input('id')]
        );

        return response()->json(['id' => $request->input('id')]);
    }

    public function createSubfolder(Request $request)
    {
        $token = $this->applyUserToken();
        $parentId = $token->recordings_folder_id;

        $folderId = $this->drive->createFolder($request->input('name'), $parentId);

        if ($folder = Folder::where('google_id', $parentId)->first()) {
            Subfolder::create([
                'folder_id' => $folder->id,
                'google_id' => $folderId,
                'name'      => $request->input('name'),
            ]);
        }

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
        $this->applyUserToken();

        $this->drive->deleteFile($id);

        Subfolder::where('google_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }
    public function saveResults(Request $request)
    {
        // 1. Validación: ahora esperamos también el mime type del audio
        $v = $request->validate([
            'meetingName'            => 'required|string',
            'rootFolder'             => 'required|string',
            'transcriptionSubfolder' => 'nullable|string',
            'audioSubfolder'         => 'nullable|string',
            'transcriptionData'      => 'required',
            'analysisResults'        => 'required',
            'audioData'              => 'required|string',      // Base64
            'audioMimeType'          => 'required|string',      // p.ej. "audio/webm"
        ]);

        try {
            // 2. Carpetas en Drive
            $meetingName           = $v['meetingName'];
            $transcriptionFolderId = $v['transcriptionSubfolder'] ?: $v['rootFolder'];
            $audioFolderId         = $v['audioSubfolder']       ?: $v['rootFolder'];

            // 3. Decodifica Base64
            $b64    = $v['audioData'];
            if (str_contains($b64, ',')) {
                [, $b64] = explode(',', $b64, 2);
            }
            $raw    = base64_decode($b64);

            // 4. Guarda temporalmente y lee el binario
            $tmp   = tempnam(sys_get_temp_dir(), 'aud');
            file_put_contents($tmp, $raw);
            $audio = file_get_contents($tmp);
            @unlink($tmp);

            // 5. Prepara payload de transcripción/análisis
            $analysis = $v['analysisResults'];
            $payload  = [
                'segments'  => $v['transcriptionData'],
                'summary'   => $analysis['summary']   ?? null,
                'keyPoints' => $analysis['keyPoints'] ?? [],
                'tasks'     => $analysis['tasks']     ?? [],
            ];
            $encrypted = Crypt::encryptString(json_encode($payload));

            // 6. Sube los archivos a Drive
            //    Asume que en tu servicio GoogleDriveService tienes uploadFile()
            $transcriptFileId = $this->drive
                ->uploadFile("{$meetingName}.ju", 'application/json', $transcriptionFolderId, $encrypted);

            // extrae la extensión del mimeType, p.ej. "audio/webm" → "webm"
            [$type, $sub]     = explode('/', $v['audioMimeType'], 2);
            $ext              = preg_replace('/[^\w]/', '', $sub);

            $audioFileId      = $this->drive
                ->uploadFile("{$meetingName}.{$ext}", $v['audioMimeType'], $audioFolderId, $audio);

            $transcriptUrl = $this->drive->getFileLink($transcriptFileId);
            $audioUrl      = $this->drive->getFileLink($audioFileId);

            // 7. Guarda en BD y responde
            TranscriptionLaravel::create([
                'username'            => Auth::user()->username,
                'meeting_name'        => $meetingName,
                'audio_file_id'       => $audioFileId,
                'audio_file_url'      => $audioUrl,
                'transcript_file_id'  => $transcriptFileId,
                'transcript_file_url' => $transcriptUrl,
            ]);

            return response()->json([
                'saved'               => true,
                'audio_file_id'       => $audioFileId,
                'audio_file_url'      => $audioUrl,
                'transcript_file_id'  => $transcriptFileId,
                'transcript_file_url' => $transcriptUrl,
            ], 200);

        } catch (RuntimeException $e) {
            Log::error('saveResults failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('saveResults failed', ['exception' => $e->getMessage()]);
            return response()->json(['message' => 'Error interno'], 500);
        }
    }
}
