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

class DriveController extends Controller
{
    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    protected function applyUserToken(): GoogleToken
    {
        $token = GoogleToken::where('username', Auth::user()->username)
            ->whereNotNull('access_token')
            ->firstOrFail();

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
        $token = $this->applyUserToken();

        $folderId = $request->input('id');

        $token->update(['recordings_folder_id' => $folderId]);

        $folderName = Folder::where('google_id', $folderId)->value('name');

        if (!$folderName) {
            try {
                $file = $this->drive->getDrive()->files->get($folderId, ['fields' => 'name']);
                $folderName = $file->getName();
            } catch (\Throwable $e) {
                $folderName = 'recordings_' . Auth::user()->username;
            }
        }

        Folder::updateOrCreate(
            [
                'google_token_id' => $token->id,
                'google_id'       => $folderId,
            ],
            [
                'name'      => $folderName,
                'parent_id' => null,
            ]
        );

        return response()->json(['id' => $folderId, 'name' => $folderName]);
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

    public function deleteSubfolder(string $id)
    {
        $this->applyUserToken();
        $this->drive->deleteFile($id);
        Subfolder::where('google_id', $id)->delete();

        return response()->json(['deleted' => true]);
    }

    public function syncDriveSubfolders()
    {
        // 1. Obtener el GoogleToken del usuario autenticado
        $username = Auth::user()->username;
        $token    = GoogleToken::where('username', $username)
            ->whereNotNull('access_token')
            ->firstOrFail();

        // 2. Validar que recordings_folder_id no sea nulo
        if (!$token->recordings_folder_id) {
            abort(400, 'El usuario no tiene configurada la carpeta principal');
        }

        // 3. Crear cliente de Drive usando GoogleAuthController::createClient()
        $authController = app(\App\Http\Controllers\Auth\GoogleAuthController::class);
        $clientMethod   = new \ReflectionMethod($authController, 'createClient');
        $clientMethod->setAccessible(true);
        /** @var \Google\Client $client */
        $client = $clientMethod->invoke($authController);

        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);

        $drive = new \Google\Service\Drive($client);

        // 4. Garantizar la existencia de la carpeta raíz con su nombre correcto
        try {
            $file       = $drive->files->get($token->recordings_folder_id, ['fields' => 'name']);
            $folderName = $file->getName() ?? "recordings_{$username}";
        } catch (\Throwable $e) {
            $folderName = "recordings_{$username}";
        }

        $rootFolder = Folder::updateOrCreate(
            [
                'google_token_id' => $token->id,
                'google_id'       => $token->recordings_folder_id,
            ],
            [
                'name'      => $folderName,
                'parent_id' => null,
            ]
        );

        // 5. Listar subcarpetas de primer nivel dentro de recordings_folder_id
        $query   = 'mimeType="application/vnd.google-apps.folder" and "' . $token->recordings_folder_id . '" in parents';
        $results = $drive->files->listFiles([
            'q'      => $query,
            'fields' => 'files(id,name)',
        ]);

        $subfolders = [];
        foreach ($results->getFiles() as $file) {
            $subfolders[] = Subfolder::updateOrCreate(
                [
                    'folder_id' => $rootFolder->id,
                    'google_id' => $file->getId(),
                ],
                ['name' => $file->getName()]
            );
        }

        // 7. Devolver JSON con la carpeta raíz y subcarpetas sincronizadas
        return response()->json([
            'root_folder' => $rootFolder,
            'subfolders'  => $subfolders,
        ]);
    }

    public function status()
    {
        $token = GoogleToken::where('username', Auth::user()->username)->first();
        if (!$token || !$token->access_token) {
            return response()->json(['connected' => false, 'calendar' => false]);
        }

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

        $calendarService = new \App\Services\GoogleCalendarService();
        $calendarClient  = $calendarService->getClient();
        $calendarClient->setAccessToken($client->getAccessToken());

        try {
            $calendarService->getCalendar()->calendarList->get('primary');
            $calendarConnected = true;
        } catch (\Throwable $e) {
            $calendarConnected = false;
        }

        return response()->json(['connected' => true, 'calendar' => $calendarConnected]);
    }
}
