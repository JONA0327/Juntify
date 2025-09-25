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

            // Ensure current user has Drive access on the group's folder based on role
            try {
                $role = $this->userCanManageGroupDocuments($user->id, $group) ? 'writer' : 'reader';
                if (!empty($user->email)) {
                    $this->driveHelper->getDrive()->shareItem($folder->google_id, $user->email, $role);
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not ensure Drive permission for user on group folder', [
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $files = $this->driveHelper->getDrive()->listFilesInFolder($folder->google_id);

            $canManage = $this->userCanManageGroupDocuments($user->id, $group);
            $canView = true; // ya pasó el guard anterior

            return response()->json([
                'folder' => [
                    'id' => $folder->id,
                    'google_id' => $folder->google_id,
                    'name' => $folder->name,
                ],
                'permissions' => [
                    'can_view' => $canView,
                    'can_manage' => $canManage,
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
            // Laravel's max for files is in kilobytes: 150 MB = 150 * 1024 = 153600 KB
            'file' => 'required|file|max:153600', // 150 MB
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

        // Permitir administradores y colaboradores de la organización
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

            // Calcular flags de permisos (gestión sólo para administradores; vista permitida por el guard)
            $canManage = ($organization->admin_id === $user->id) || $organization->users()
                ->where('users.id', $user->id)
                ->wherePivot('rol', 'administrador')
                ->exists();
            // Ensure current user has at least reader access to the organization's root folder
            try {
                if (!empty($user->email)) {
                    $this->driveHelper->getDrive()->shareItem($root->google_id, $user->email, $canManage ? 'writer' : 'reader');
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not ensure Drive permission for user on org root', [
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            return response()->json([
                'root' => [
                    'id' => $root->id,
                    'google_id' => $root->google_id,
                    'name' => $root->name,
                ],
                'permissions' => [
                    'can_view' => true,
                    'can_manage' => (bool) $canManage,
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
            // Share this specific folder with current user according to role
            try {
                $canManage = ($organization->admin_id === $user->id) || $organization->users()
                    ->where('users.id', $user->id)
                    ->wherePivot('rol', 'administrador')
                    ->exists();
                if (!empty($user->email)) {
                    $this->driveHelper->getDrive()->shareItem($folderId, $user->email, $canManage ? 'writer' : 'reader');
                }
            } catch (\Throwable $e) {
                \Log::warning('Could not ensure Drive permission for user on subfolder', [
                    'organization_id' => $organization->id,
                    'folder_id' => $folderId,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            // Determinar si el usuario puede gestionar (administrar) a nivel de organización
            $canManage = ($organization->admin_id === $user->id) || $organization->users()
                ->where('users.id', $user->id)
                ->wherePivot('rol', 'administrador')
                ->exists();

            return response()->json([
                'permissions' => [
                    'can_view' => true,
                    'can_manage' => (bool) $canManage,
                ],
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

    protected function userCanViewGroupDocuments(string $userId, Group $group): bool
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

    protected function userCanManageGroupDocuments(string $userId, Group $group): bool
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

    protected function userIsOrganizationAdmin(string $userId, Organization $organization): bool
    {
        // Admin directo
        if ($organization->admin_id === $userId) {
            return true;
        }

        // Colaboradores y administradores (enlace de usuarios a organización)
        $isPrivileged = $organization->users()
            ->where('users.id', $userId)
            ->wherePivotIn('rol', ['colaborador', 'administrador'])
            ->exists();
        if ($isPrivileged) {
            return true;
        }

        // Permitir miembros de grupos de esta organización como lectores
        $hasGroupMembership = Group::where('organization_id', $organization->id)
            ->whereHas('users', function($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->exists();

        return $hasGroupMembership;
    }

    protected function formatDriveFile(DriveFile $file): array
    {
        // Try to get a webContentLink as a fallback download URL
        $webContentLink = null;
        try {
            $webContentLink = $this->driveHelper->getDrive()->getWebContentLink($file->getId());
        } catch (\Throwable $e) {
            // ignore
        }
        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mimeType' => $file->getMimeType(),
            'webViewLink' => $file->getWebViewLink(),
            'webContentLink' => $webContentLink,
            'iconLink' => $file->getIconLink(),
            'size' => $file->getSize(),
            'createdTime' => $file->getCreatedTime(),
            'modifiedTime' => $file->getModifiedTime(),
        ];
    }
}

