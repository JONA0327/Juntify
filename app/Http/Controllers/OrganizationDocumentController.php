<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupDriveFolder;
use App\Models\Organization;
use App\Models\OrganizationSubfolder;
use App\Services\OrganizationDriveHelper;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationDocumentController extends Controller
{
    protected OrganizationDriveHelper $driveHelper;

    public function __construct(OrganizationDriveHelper $driveHelper)
    {
        $this->driveHelper = $driveHelper;
    }

    public function listGroupDocuments(Group $group): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        $organization = $group->organization;
        if (!$organization) {
            abort(404);
        }

        if (!$this->userCanViewGroupDocuments($user->id, $group)) {
            abort(403);
        }

        try {
            $this->driveHelper->initDrive($organization);
            $folder = $this->driveHelper->ensureGroupFolder($group);

            $files = $this->driveHelper->getDrive()->listFilesInFolder($folder->google_id);

            return response()->json([
                'folder' => [
                    'id' => $folder->id,
                    'google_id' => $folder->google_id,
                    'name' => $folder->name,
                ],
                'files' => array_map(fn(DriveFile $file) => $this->formatDriveFile($file), $files),
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudieron listar los documentos del grupo', [
                'group_id' => $group->id,
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo obtener la lista de documentos del grupo',
            ], 502);
        }
    }

    public function uploadGroupDocument(Request $request, Group $group): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        $organization = $group->organization;
        if (!$organization) {
            abort(404);
        }

        if (!$this->userCanManageGroupDocuments($user->id, $group)) {
            abort(403);
        }

        $validated = $request->validate([
            'file' => 'required|file|max:51200', // 50 MB
        ]);

        $uploaded = $validated['file'];

        try {
            $this->driveHelper->initDrive($organization);
            $folder = $this->driveHelper->ensureGroupFolder($group);

            $contents = file_get_contents($uploaded->getRealPath());
            $mimeType = $uploaded->getClientMimeType() ?: 'application/octet-stream';
            $fileId = $this->driveHelper->getDrive()->uploadFile(
                $uploaded->getClientOriginalName(),
                $mimeType,
                $folder->google_id,
                $contents
            );

            $info = $this->driveHelper->getDrive()->getFileInfo($fileId);

            return response()->json([
                'message' => 'Documento subido correctamente',
                'file' => $this->formatDriveFile($info),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al subir documento de grupo', [
                'group_id' => $group->id,
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo subir el documento',
            ], 502);
        }
    }

    public function listOrganizationFolders(Organization $organization): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        if (!$this->userIsOrganizationAdmin($user->id, $organization)) {
            abort(403);
        }

        try {
            $this->driveHelper->initDrive($organization);
            $organization->loadMissing('folder');
            $root = $organization->folder;
            if (!$root) {
                return response()->json([
                    'folders' => [],
                    'root' => null,
                ]);
            }

            $folders = $this->driveHelper->getDrive()->listSubfolders($root->google_id);
            $mapped = array_map(function (DriveFile $folder) use ($organization) {
                $record = OrganizationSubfolder::updateOrCreate(
                    [
                        'organization_folder_id' => optional($organization->folder)->id,
                        'google_id' => $folder->getId(),
                    ],
                    ['name' => $folder->getName()]
                );

                $groupLink = GroupDriveFolder::with('group')
                    ->where('google_id', $folder->getId())
                    ->first();

                return [
                    'id' => $record->id,
                    'google_id' => $folder->getId(),
                    'name' => $folder->getName(),
                    'group' => $groupLink && $groupLink->group
                        ? [
                            'id' => $groupLink->group->id,
                            'nombre_grupo' => $groupLink->group->nombre_grupo,
                        ]
                        : null,
                ];
            }, $folders);

            return response()->json([
                'root' => [
                    'id' => $root->id,
                    'google_id' => $root->google_id,
                    'name' => $root->name,
                ],
                'folders' => $mapped,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al listar carpetas de la organización', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudieron obtener las carpetas de la organización',
            ], 502);
        }
    }

    public function showOrganizationFolder(Organization $organization, string $folderId): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        if (!$this->userIsOrganizationAdmin($user->id, $organization)) {
            abort(403);
        }

        try {
            $this->driveHelper->initDrive($organization);

            $organization->loadMissing('folder');
            $root = $organization->folder;
            if (!$root) {
                abort(404);
            }

            $available = $this->driveHelper->getDrive()->listSubfolders($root->google_id);
            $ids = array_map(fn(DriveFile $file) => $file->getId(), $available);
            if (!in_array($folderId, $ids, true)) {
                abort(404);
            }

            $files = $this->driveHelper->getDrive()->listFilesInFolder($folderId);

            return response()->json([
                'files' => array_map(fn(DriveFile $file) => $this->formatDriveFile($file), $files),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener contenido de carpeta de organización', [
                'organization_id' => $organization->id,
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo obtener el contenido de la carpeta solicitada',
            ], 502);
        }
    }

    protected function userCanViewGroupDocuments(int $userId, Group $group): bool
    {
        $organization = $group->organization;
        if ($organization && $organization->admin_id === $userId) {
            return true;
        }

        $isOrgAdmin = $organization?->users()
            ->where('users.id', $userId)
            ->wherePivotIn('rol', ['colaborador', 'administrador'])
            ->exists();

        if ($isOrgAdmin) {
            return true;
        }

        return $group->users()
            ->where('users.id', $userId)
            ->exists();
    }

    protected function userCanManageGroupDocuments(int $userId, Group $group): bool
    {
        $organization = $group->organization;
        if ($organization && $organization->admin_id === $userId) {
            return true;
        }

        $isOrgAdmin = $organization?->users()
            ->where('users.id', $userId)
            ->wherePivot('rol', 'administrador')
            ->exists();

        if ($isOrgAdmin) {
            return true;
        }

        $membership = $group->users()
            ->where('users.id', $userId)
            ->first();

        $role = $membership?->pivot?->rol;

        return in_array($role, ['colaborador', 'administrador'], true);
    }

    protected function userIsOrganizationAdmin(int $userId, Organization $organization): bool
    {
        if ($organization->admin_id === $userId) {
            return true;
        }

        return $organization->users()
            ->where('users.id', $userId)
            ->wherePivotIn('rol', ['colaborador', 'administrador'])
            ->exists();
    }

    protected function formatDriveFile(DriveFile $file): array
    {
        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mimeType' => $file->getMimeType(),
            'webViewLink' => $file->getWebViewLink(),
            'iconLink' => $file->getIconLink(),
            'size' => $file->getSize(),
            'createdTime' => $file->getCreatedTime(),
            'modifiedTime' => $file->getModifiedTime(),
        ];
    }
}

