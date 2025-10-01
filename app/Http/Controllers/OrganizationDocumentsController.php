<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\MeetingContentContainer;
use App\Models\Organization;
use App\Models\OrganizationContainerFolder;
use App\Models\OrganizationGroupFolder;
use App\Services\GoogleDriveService;
use App\Services\OrganizationDriveHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationDocumentsController extends Controller
{
    public function __construct(
        protected GoogleDriveService $drive,
        protected OrganizationDriveHierarchyService $hierarchy
    ) {}

    protected function user(): ?\App\Models\User { return auth()->user(); }

    protected function ensureOrgToken(Organization $organization): void
    {
        $token = $organization->googleToken;
        if (!$token || !$token->isConnected()) {
            abort(409, 'Organización sin conexión Google Drive');
        }
        $client = $this->drive->getClient();
        $client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date' => $token->expiry_date,
        ]);
        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date' => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            } else {
                abort(409, 'Token inválido, reconectar');
            }
        }
    }

    protected function authorizeGroupAccess(Organization $organization, Group $group, bool $write = false): void
    {
        if ($group->id_organizacion !== $organization->id) abort(404);
        $user = $this->user();
        if (!$user) abort(401);
        // Miembro del grupo
        $isMember = $group->users()->where('users.id', $user->id)->exists();
        if (!$isMember) abort(403, 'No pertenece al grupo');
        if (!$write) return;
        // Rol escritor
        $writer = $group->users()->where('users.id', $user->id)->whereIn('group_user.rol', ['colaborador','administrador'])->exists();
        if (!$writer) abort(403, 'Permiso insuficiente');
    }

    public function listGroups(Organization $organization)
    {
        $user = $this->user();
        if (!$user) abort(401);
        // grupos donde el usuario pertenece
        $groups = $organization->groups()->whereHas('users', function($q) use ($user) { $q->where('users.id',$user->id); })->get();
        $data = [];
        foreach ($groups as $g) {
            $folder = OrganizationGroupFolder::where('group_id', $g->id)->first();
            $data[] = [
                'id' => $g->id,
                'name' => $g->nombre_grupo ?? $g->name ?? ('Grupo '.$g->id),
                'folder_id' => $folder?->google_id,
            ];
        }
        return response()->json(['groups' => $data]);
    }

    public function ensureGroupFolder(Organization $organization, Group $group)
    {
        $this->authorizeGroupAccess($organization, $group, false);
        // Aseguramos token OAuth de la organización para que listSubfolders y creación vía oauth funcionen.
        // (Si falla, seguirá intentando estrategias de service account dentro del servicio.)
        try {
            $this->ensureOrgToken($organization);
        } catch (\Throwable $e) {
            Log::warning('ensureGroupFolder: no OAuth token available, continuing with service account fallbacks', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
        }

        $model = $this->hierarchy->ensureGroupFolder($organization, $group);
        if (!$model) {
            Log::warning('ensureGroupFolder: hierarchy service returned null (group folder not created)', [
                'org_id' => $organization->id,
                'group_id' => $group->id,
            ]);
        }
        return response()->json([
            'group_id' => $group->id,
            'folder_id' => $model?->google_id,
            'created' => $model?->wasRecentlyCreated ?? false,
            'status' => $model ? 'ok' : 'not_created'
        ]);
    }

    public function listContainers(Organization $organization, Group $group)
    {
        $this->authorizeGroupAccess($organization, $group, false);
        // listar contenedores activos
        $containers = $group->containers()->get();
        $data = [];
        foreach ($containers as $c) {
            $folder = OrganizationContainerFolder::where('container_id', $c->id)->first();
            $data[] = [
                'id' => $c->id,
                'name' => $c->name ?? $c->title ?? ('Container '.$c->id),
                'folder_id' => $folder?->google_id,
            ];
        }
        return response()->json(['containers' => $data]);
    }

    public function ensureContainerFolder(Organization $organization, Group $group, MeetingContentContainer $container)
    {
        $this->authorizeGroupAccess($organization, $group, false);
        if ($container->group_id !== $group->id) abort(404);
        try {
            $this->ensureOrgToken($organization);
        } catch (\Throwable $e) {
            Log::warning('ensureContainerFolder: no OAuth token available, continuing with service account fallbacks', [
                'org_id' => $organization->id,
                'group_id' => $group->id,
                'container_id' => $container->id,
                'error' => $e->getMessage(),
            ]);
        }
        $model = $this->hierarchy->ensureContainerFolder($organization, $group, $container);
        if (!$model) {
            Log::warning('ensureContainerFolder: hierarchy service returned null (container folder not created)', [
                'org_id' => $organization->id,
                'group_id' => $group->id,
                'container_id' => $container->id,
            ]);
        }
        return response()->json([
            'container_id' => $container->id,
            'folder_id' => $model?->google_id,
            'created' => $model?->wasRecentlyCreated ?? false,
            'status' => $model ? 'ok' : 'not_created'
        ]);
    }

    public function listContainerFiles(Organization $organization, Group $group, MeetingContentContainer $container)
    {
        $this->authorizeGroupAccess($organization, $group, false);
        if ($container->group_id !== $group->id) abort(404);
        $folder = OrganizationContainerFolder::where('container_id', $container->id)->first();
        if (!$folder) {
            return response()->json(['files' => [], 'folder_missing' => true]);
        }
        $this->ensureOrgToken($organization);
        try {
            $files = $this->drive->searchFiles("", $folder->google_id); // retorna todos
            $out = [];
            foreach ($files as $f) {
                $out[] = [
                    'id' => $f->getId(),
                    'name' => $f->getName(),
                ];
            }
            return response()->json(['files' => $out]);
        } catch (\Throwable $e) {
            Log::warning('List container files failed', [
                'container_id' => $container->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['files' => [], 'error' => 'drive_list_failed'], 502);
        }
    }

    public function uploadToContainer(Organization $organization, Group $group, MeetingContentContainer $container, Request $request)
    {
        $this->authorizeGroupAccess($organization, $group, true); // requiere write
        if ($container->group_id !== $group->id) abort(404);
        $request->validate([
            'file' => 'required|file|max:153600', // 150MB = 153600 KB en validación Laravel (KB)
        ]);

        $folder = OrganizationContainerFolder::where('container_id', $container->id)->first();
        if (!$folder) {
            $folderModel = $this->hierarchy->ensureContainerFolder($organization, $group, $container);
            if (!$folderModel) abort(500, 'No se pudo crear carpeta');
            $folder = $folderModel;
        }

        $this->ensureOrgToken($organization);
        $file = $request->file('file');
        $name = $file->getClientOriginalName();
        $mime = $file->getMimeType();
        $contents = file_get_contents($file->getRealPath());

        try {
            $fileId = $this->drive->uploadFile($name, $mime, $folder->google_id, $contents);
            return response()->json(['file_id' => $fileId, 'name' => $name]);
        } catch (\Throwable $e) {
            Log::error('Upload to container failed', [
                'container_id' => $container->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'upload_failed', 'error' => $e->getMessage()], 502);
        }
    }
}
