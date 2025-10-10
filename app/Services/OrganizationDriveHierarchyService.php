<?php

namespace App\Services;

use App\Models\Group;
use App\Models\MeetingContentContainer;
use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationGroupFolder;
use App\Models\OrganizationContainerFolder;
use App\Models\OrganizationSubfolder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrganizationDriveHierarchyService
{
    public function __construct(
        protected GoogleDriveService $drive,
        protected GoogleServiceAccount $serviceAccount
    ) {}

    /**
     * Asegura que exista la carpeta "Grupos" dentro de Documentos y devuelve su ID.
     */
    public function ensureGruposRoot(Organization $organization): ?string
    {
        try {
            // Encontrar subfolder 'Documentos'
            $documentos = OrganizationSubfolder::whereHas('folder', function($q) use ($organization) {
                $q->where('organization_id', $organization->id);
            })->where('name', 'Documentos')->first();

            if (! $documentos) {
                // Intentar recrear si la raíz existe pero se perdió el registro o la carpeta
                $root = OrganizationFolder::where('organization_id', $organization->id)->first();
                if (!$root) {
                    // Crear registro base si ni siquiera existe fila
                    $root = OrganizationFolder::create([
                        'organization_id' => $organization->id,
                        'google_id' => null,
                        'name' => 'root',
                    ]);
                    Log::warning('Hierarchy: root folder DB record was missing; created placeholder', ['org_id' => $organization->id]);
                }
                // Asegurar que root->google_id exista (autogenerar si null)
                if (!$root->google_id) {
                    $createdRootId = $this->ensureRootFolder($organization, $root);
                    if (!$createdRootId) {
                        Log::warning('Hierarchy: unable to auto-create root google folder', ['org_id' => $organization->id]);
                        return null;
                    }
                    try {
                        $root->google_id = $createdRootId;
                        $root->save();
                        // Verificar persistencia inmediata
                        $check = OrganizationFolder::find($root->id);
                        if (!$check || $check->google_id !== $createdRootId) {
                            Log::error('Hierarchy: root google_id not persisted after save', [
                                'org_id' => $organization->id,
                                'root_id' => $root->id,
                                'expected' => $createdRootId,
                                'found' => $check?->google_id,
                            ]);
                            return null;
                        }
                        Log::info('Hierarchy: root google_id persisted', [
                            'org_id' => $organization->id,
                            'root_id' => $root->id,
                            'google_id' => $createdRootId,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Hierarchy: failed saving root google_id', [
                            'org_id' => $organization->id,
                            'error' => $e->getMessage(),
                        ]);
                        return null;
                    }
                }
                Log::warning('Hierarchy: Documentos subfolder missing, attempting auto-create', ['org_id' => $organization->id]);
                // Estrategias similares a creación estándar
                $docFolderId = null;
                $strategies = ['service_account_direct', 'service_account_impersonate', 'oauth'];
                foreach ($strategies as $strategy) {
                    try {
                        $docFolderId = $this->createFolderStrategy('Documentos', $root->google_id, $organization, $strategy);
                        if ($docFolderId) break;
                    } catch (Throwable $e) {
                        Log::debug('Hierarchy: failed recreate Documentos with strategy', [
                            'strategy' => $strategy,
                            'org_id' => $organization->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                if ($docFolderId) {
                    try {
                        $documentos = OrganizationSubfolder::create([
                            'organization_folder_id' => $root->id,
                            'google_id' => $docFolderId,
                            'name' => 'Documentos',
                        ]);
                        Log::info('Hierarchy: recreated Documentos subfolder', ['org_id' => $organization->id]);
                    } catch (Throwable $e) {
                        Log::error('Hierarchy: failed to persist recreated Documentos', [
                            'org_id' => $organization->id,
                            'error' => $e->getMessage(),
                        ]);
                        return null;
                    }
                } else {
                    return null; // no se pudo recrear
                }
            }

            $documentosDriveId = $documentos->google_id;
            $targetName = 'Grupos';

            // Buscar si ya existe 'Grupos'
            $subfolders = $this->drive->listSubfolders($documentosDriveId);
            foreach ($subfolders as $sf) {
                if (strcasecmp($sf->getName(), $targetName) === 0) {
                    return $sf->getId();
                }
            }

            // Crear usando cascada de estrategias
            $strategies = ['service_account_direct', 'service_account_impersonate', 'oauth'];
            foreach ($strategies as $strategy) {
                try {
                    $folderId = $this->createFolderStrategy($targetName, $documentosDriveId, $organization, $strategy);
                    if ($folderId) {
                        return $folderId;
                    }
                } catch (Throwable $e) {
                    Log::warning('Hierarchy: strategy failed creating Grupos', [
                        'strategy' => $strategy,
                        'org_id' => $organization->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return null;
        } catch (Throwable $e) {
            Log::error('Hierarchy: ensureGruposRoot fatal', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Asegura carpeta de un Group y devuelve su modelo.
     */
    public function ensureGroupFolder(Organization $organization, Group $group): ?OrganizationGroupFolder
    {
        $existing = OrganizationGroupFolder::where('group_id', $group->id)->first();
        if ($existing) {
            return $existing;
        }

        $gruposRootId = $this->ensureGruposRoot($organization);
        if (! $gruposRootId) {
            return null;
        }

    // El modelo Group usa 'nombre_grupo' como campo real
    $slug = Str::slug($group->nombre_grupo ?: 'grupo');
        $folderName = $slug.'-'.$group->id;

        $folderId = $this->createWithFallback($folderName, $gruposRootId, $organization);
        if (! $folderId) {
            return null;
        }
        $model = OrganizationGroupFolder::create([
            'organization_id' => $organization->id,
            'group_id' => $group->id,
            'organization_folder_id' => null, // opcional referencia a Documentos si luego se requiere
            'google_id' => $folderId,
            'name' => $folderName,
            'path_cached' => 'Documentos/Grupos/'.$folderName,
        ]);

        // Asignar permisos a miembros del grupo
        $this->assignGroupPermissions($organization, $group, $folderId);

        return $model;
    }

    /**
     * Asegura carpeta de un Container dentro de su group folder.
     */
    public function ensureContainerFolder(Organization $organization, Group $group, MeetingContentContainer $container): ?OrganizationContainerFolder
    {
        $existing = OrganizationContainerFolder::where('container_id', $container->id)->first();
        if ($existing) {
            return $existing;
        }

        $groupFolder = $this->ensureGroupFolder($organization, $group);
        if (! $groupFolder) {
            return null;
        }

    // MeetingContentContainer tiene campo 'name', no 'title'
    $slug = Str::slug($container->name ?: ('container-'.$container->id));
        $folderName = $slug.'-'.$container->id;

        $folderId = $this->createWithFallback($folderName, $groupFolder->google_id, $organization);
        if (! $folderId) {
            return null;
        }
        $model = OrganizationContainerFolder::create([
            'organization_id' => $organization->id,
            'group_id' => $group->id,
            'container_id' => $container->id,
            'organization_group_folder_id' => $groupFolder->id,
            'google_id' => $folderId,
            'name' => $folderName,
            'path_cached' => 'Documentos/Grupos/'.$groupFolder->name.'/'.$folderName,
        ]);

        // Permisos: solo miembros del grupo, roles writer si colaborador/admin, invitado reader
        $this->assignContainerPermissions($organization, $group, $container, $folderId);

        return $model;
    }

    protected function createWithFallback(string $name, string $parentId, Organization $organization): ?string
    {
        $strategies = ['service_account_direct', 'service_account_impersonate', 'oauth'];
        foreach ($strategies as $strategy) {
            try {
                $id = $this->createFolderStrategy($name, $parentId, $organization, $strategy);
                if ($id) {
                    return $id;
                }
            } catch (Throwable $e) {
                Log::warning('Hierarchy: createWithFallback strategy failed', [
                    'strategy' => $strategy,
                    'name' => $name,
                    'org_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return null;
    }

    protected function createFolderStrategy(string $name, string $parentId, Organization $organization, string $strategy): ?string
    {
        switch ($strategy) {
            case 'service_account_direct':
                return $this->serviceAccount->createFolder($name, $parentId);
            case 'service_account_impersonate':
                if (! \App\Services\GoogleServiceAccount::impersonationDisabled()) {
                    $adminEmail = $organization->owner->email ?? null;
                    if ($adminEmail) {
                        $this->serviceAccount->impersonate($adminEmail);
                        return $this->serviceAccount->createFolder($name, $parentId);
                    }
                }
                return null;
            case 'oauth':
                // Intentar con OAuth token de la organización si existe
                $orgFolder = OrganizationFolder::where('organization_id', $organization->id)->first();
                if ($orgFolder && $orgFolder->organization_google_token_id) {
                    try {
                        // Reutilizamos drive service ya inicializado (asumiendo token seteado externamente donde se llame)
                        return $this->drive->createFolder($name, $parentId);
                    } catch (Throwable $e) {
                        Log::warning('Hierarchy: oauth strategy failed', [
                            'org_id' => $organization->id,
                            'error' => $e->getMessage(),
                        ]);
                        return null;
                    }
                }
                return null;
            default:
                return null;
        }
    }

    protected function assignGroupPermissions(Organization $organization, Group $group, string $folderId): void
    {
        try {
            $members = $group->users()->withPivot('rol')->get();
            foreach ($members as $user) {
                $role = $user->pivot->rol;
                $driveRole = match ($role) {
                    Group::ROLE_ADMINISTRADOR, Group::ROLE_COLABORADOR => 'writer',
                    default => 'reader',
                };
                $this->shareItemWithFallback($folderId, $user->email, $driveRole, $organization);
            }
        } catch (Throwable $e) {
            Log::warning('Hierarchy: assignGroupPermissions failed', [
                'group_id' => $group->id,
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function assignContainerPermissions(Organization $organization, Group $group, MeetingContentContainer $container, string $folderId): void
    {
        try {
            $members = $group->users()->withPivot('rol')->get();
            foreach ($members as $user) {
                $role = $user->pivot->rol;
                $driveRole = match ($role) {
                    Group::ROLE_ADMINISTRADOR, Group::ROLE_COLABORADOR => 'writer',
                    default => 'reader',
                };
                $this->shareItemWithFallback($folderId, $user->email, $driveRole, $organization);
            }
        } catch (Throwable $e) {
            Log::warning('Hierarchy: assignContainerPermissions failed', [
                'group_id' => $group->id,
                'container_id' => $container->id,
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Comparte un archivo de contenedor con los miembros del grupo utilizando los mismos fallbacks
     * (service account y OAuth) que se usan para carpetas.
     */
    public function shareContainerFileWithGroupMembers(
        MeetingContentContainer $container,
        string $driveFileId,
        ?int $skipUserId = null
    ): void {
        $group = $container->group;
        $organization = $group?->organization;

        if (! $group || ! $organization) {
            return;
        }

        try {
            $members = $group->users()->withPivot('rol')->get();
            foreach ($members as $user) {
                if (! $user->email) {
                    continue;
                }

                if ($skipUserId !== null && $user->id === $skipUserId) {
                    continue;
                }

                $role = $user->pivot->rol ?? null;
                $driveRole = match ($role) {
                    Group::ROLE_ADMINISTRADOR, Group::ROLE_COLABORADOR => 'writer',
                    default => 'reader',
                };

                $this->shareItemWithFallback($driveFileId, $user->email, $driveRole, $organization);
            }
        } catch (Throwable $e) {
            Log::warning('Hierarchy: shareContainerFileWithGroupMembers failed', [
                'group_id' => $group->id,
                'container_id' => $container->id,
                'org_id' => $organization->id,
                'drive_file_id' => $driveFileId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shareItemWithFallback(string $itemId, string $email, string $driveRole, Organization $organization): void
    {
        $strategies = ['service_account_impersonate', 'service_account_direct', 'oauth'];
        foreach ($strategies as $strategy) {
            try {
                switch ($strategy) {
                    case 'service_account_impersonate':
                        if (! \App\Services\GoogleServiceAccount::impersonationDisabled()) {
                            $adminEmail = $organization->owner->email ?? null;
                            if ($adminEmail) {
                                $this->serviceAccount->impersonate($adminEmail);
                                $this->serviceAccount->shareItem($itemId, $email, $driveRole);
                                return;
                            }
                        }
                        break;
                    case 'service_account_direct':
                        $this->serviceAccount->impersonate(null); // limpiar subject
                        $this->serviceAccount->shareItem($itemId, $email, $driveRole);
                        return;
                    case 'oauth':
                        try {
                            $this->drive->shareItem($itemId, $email, $driveRole);
                            return;
                        } catch (Throwable $e) {
                            // noop, continuará fallback
                        }
                        break;
                }
            } catch (Throwable $e) {
                Log::debug('Hierarchy: share strategy failed', [
                    'strategy' => $strategy,
                    'email' => $email,
                    'role' => $driveRole,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
        Log::warning('Hierarchy: all share strategies failed', [
            'item_id' => $itemId,
            'email' => $email,
            'role' => $driveRole,
        ]);
    }

    /**
     * Asegura (o crea) la carpeta raíz de la organización en Drive cuando falta google_id.
     * Estrategia: intentar crear una carpeta de nombre único y devolver su ID.
     */
    protected function ensureRootFolder(Organization $organization, OrganizationFolder $rootRecord): ?string
    {
        try {
            // Nombre único predecible: ORG_{id}_{slug}
            $slug = Str::slug($organization->nombre_organizacion ?? $organization->name ?? ('org-'.$organization->id));
            $rootName = 'ORG_'.$organization->id.'_'.$slug;
            $strategies = ['service_account_direct', 'service_account_impersonate', 'oauth'];
            foreach ($strategies as $strategy) {
                try {
                    $id = $this->createFolderStrategy($rootName, 'root', $organization, $strategy); // parent 'root' en Drive
                    if ($id) {
                        Log::info('Hierarchy: root folder (google) created', [
                            'org_id' => $organization->id,
                            'strategy' => $strategy,
                            'folder_id' => $id,
                        ]);
                        return $id;
                    }
                } catch (Throwable $e) {
                    Log::warning('Hierarchy: strategy failed creating root folder', [
                        'org_id' => $organization->id,
                        'strategy' => $strategy,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return null;
        } catch (Throwable $e) {
            Log::error('Hierarchy: ensureRootFolder fatal', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
