<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriveController extends Controller
{
    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    public function authorizeService()
    {
        $client = $this->drive->getClient();
        $token  = $client->fetchAccessTokenWithAssertion();

        GoogleToken::updateOrCreate(
            ['username' => Auth::user()->username],
            [
                'access_token'  => $token['access_token'] ?? '',
                'refresh_token' => $token['refresh_token'] ?? '',
                'expiry_date'   => now()->addSeconds($token['expires_in'] ?? 3600),
            ]
        );

        return response()->json(['status' => 'authorized']);
    }

    public function createMainFolder(Request $request)
    {
        $folderId = $this->drive->createFolder($request->input('name'), config('drive.root_folder_id'));

        GoogleToken::where('username', Auth::user()->username)
            ->update(['recordings_folder_id' => $folderId]);

        return response()->json(['id' => $folderId]);
    }

    public function setMainFolder(Request $request)
    {
        GoogleToken::updateOrCreate(
            ['username' => Auth::user()->username],
            ['recordings_folder_id' => $request->input('id')]
        );

        return response()->json(['id' => $request->input('id')]);
    }

    public function createSubfolder(Request $request)
    {
        $parentId = GoogleToken::where('username', Auth::user()->username)->value('recordings_folder_id');
        $folderId = $this->drive->createFolder($request->input('name'), $parentId);

        return response()->json(['id' => $folderId]);
    }
}
