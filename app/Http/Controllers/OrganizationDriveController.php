<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\GoogleToken;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;

class OrganizationDriveController extends Controller
{
    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    protected function initAdminDrive(Organization $organization): GoogleToken
    {
        $admin = $organization->admin;
        $token = GoogleToken::where('username', $admin->username)->firstOrFail();

        $client = $this->drive->getClient();
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);
        if ($client->isAccessTokenExpired()) {
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

    public function createRootFolder(Organization $organization)
    {
        $token = $this->initAdminDrive($organization);

        $folderId = $this->drive->createFolder($organization->nombre_organizacion);

        $folder = OrganizationFolder::create([
            'organization_id' => $organization->id,
            'google_token_id' => $token->id,
            'google_id'       => $folderId,
            'name'            => $organization->nombre_organizacion,
        ]);

        $this->drive->shareFolder(
            $folderId,
            config('services.google.service_account_email')
        );

        return response()->json(['id' => $folderId, 'folder' => $folder], 201);
    }

    public function createSubfolder(Request $request, Organization $organization)
    {
        $request->validate(['name' => 'required|string']);

        $user = $request->user();
        $isOwner = $organization->admin_id === $user->id;
        $role = $organization->users()->where('user_id', $user->id)->value('rol');

        if (! $isOwner && ! in_array($role, ['colaborador', 'administrador'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $root = $organization->folder;
        if (!$root) {
            return response()->json(['message' => 'Root folder not found'], 404);
        }

        $this->initAdminDrive($organization);

        $folderId = $this->drive->createFolder($request->input('name'), $root->google_id);

        OrganizationSubfolder::create([
            'organization_folder_id' => $root->id,
            'google_id'              => $folderId,
            'name'                   => $request->input('name'),
        ]);

        $this->drive->shareFolder(
            $folderId,
            config('services.google.service_account_email')
        );

        return response()->json(['id' => $folderId], 201);
    }

    public function listSubfolders(Organization $organization)
    {
        $root = $organization->folder;
        if (!$root) {
            return response()->json(['message' => 'Root folder not found'], 404);
        }

        $this->initAdminDrive($organization);

        $files = $this->drive->listSubfolders($root->google_id);
        $subfolders = [];
        foreach ($files as $file) {
            $subfolders[] = OrganizationSubfolder::updateOrCreate(
                [
                    'organization_folder_id' => $root->id,
                    'google_id'              => $file->getId(),
                ],
                ['name' => $file->getName()]
            );
        }

        return response()->json([
            'root_folder' => $root,
            'subfolders'  => $subfolders,
        ]);
    }
}
