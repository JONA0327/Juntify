<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use Carbon\Carbon;
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
        $this->applyUserToken();
        $folderId = $this->drive->createFolder($request->input('name'), config('drive.root_folder_id'));

        GoogleToken::where('username', Auth::user()->username)
            ->update(['recordings_folder_id' => $folderId]);

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

        return response()->json(['id' => $folderId]);
    }
}
