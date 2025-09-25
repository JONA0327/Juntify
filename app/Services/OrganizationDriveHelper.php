<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupDriveFolder;
use App\Models\MeetingContentContainer;
use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationGoogleToken;
use App\Models\OrganizationSubfolder;
use Illuminate\Support\Facades\Log;

class OrganizationDriveHelper
{
    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    public function getDrive(): GoogleDriveService
    {
        return $this->drive;
    }

    /**
     * Initialize the Google Drive client for the given organization and ensure the token is fresh.
     *
     * @throws \Exception
     */
    public function initDrive(Organization $organization): OrganizationGoogleToken
    {
        $token = $organization->googleToken;

        if (!$token) {
            throw new \Exception('La organización no tiene configurado un token de Google Drive');
        }

        if (!$token->isConnected()) {
            throw new \Exception('El token de Google Drive no está configurado correctamente');
        }

        $client = $this->drive->getClient();

        $tokenPayload = $token->getTokenArray();
        if (empty($tokenPayload['access_token'])) {
            throw new \Exception('El token de Google Drive no contiene un access token válido');
        }

        $client->setAccessToken($tokenPayload);

        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            } else {
                throw new \Exception('No se pudo renovar el token de Google Drive: ' . ($new['error'] ?? 'Error desconocido'));
            }
        }

        return $token;
    }

    /**
     * Ensure the standard "Documentos" folder exists for the organization and return it.
     */
    public function ensureDocumentRoot(Organization $organization): OrganizationSubfolder
    {
        $this->initDrive($organization);

        $organization->loadMissing('folder');
        /** @var OrganizationFolder|null $root */
        $root = $organization->folder;
        if (!$root || empty($root->google_id)) {
            throw new \RuntimeException('La organización no tiene una carpeta raíz configurada en Drive.');
        }

        $existing = OrganizationSubfolder::where('organization_folder_id', $root->id)
            ->where('name', 'Documentos')
            ->first();

        if ($existing && !empty($existing->google_id)) {
            return $existing;
        }

        $folderId = $this->drive->createFolder('Documentos', $root->google_id);

        $subfolder = OrganizationSubfolder::updateOrCreate(
            [
                'organization_folder_id' => $root->id,
                'name' => 'Documentos',
            ],
            [
                'google_id' => $folderId,
            ]
        );

        $serviceEmail = config('services.google.service_account_email');
        if ($serviceEmail) {
            try {
                $this->drive->shareFolder($folderId, $serviceEmail);
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir carpeta Documentos con la service account', [
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($adminEmail = optional($organization->admin)->email) {
            try {
                $this->drive->shareItem($folderId, $adminEmail, 'writer');
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir carpeta Documentos con el administrador de la organización', [
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $subfolder;
    }

    /**
     * Ensure a Drive folder exists for the provided group under the organization Documents folder.
     */
    public function ensureGroupFolder(Group $group): GroupDriveFolder
    {
        $group->loadMissing('organization', 'driveFolder');

        $organization = $group->organization;
        if (!$organization) {
            throw new \RuntimeException('El grupo no pertenece a ninguna organización.');
        }

        if ($group->driveFolder && !empty($group->driveFolder->google_id)) {
            return $group->driveFolder;
        }

        $documentRoot = $this->ensureDocumentRoot($organization);
        $folderName = $group->nombre_grupo ?? ('Grupo ' . $group->id);
        $folderId = $this->drive->createFolder($folderName, $documentRoot->google_id);

        $record = GroupDriveFolder::updateOrCreate(
            ['group_id' => $group->id],
            [
                'organization_subfolder_id' => $documentRoot->id,
                'google_id' => $folderId,
                'name' => $folderName,
            ]
        );

        $serviceEmail = config('services.google.service_account_email');
        if ($serviceEmail) {
            try {
                $this->drive->shareFolder($folderId, $serviceEmail);
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir la carpeta del grupo con la service account', [
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($adminEmail = optional($organization->admin)->email) {
            try {
                $this->drive->shareItem($folderId, $adminEmail, 'writer');
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir la carpeta del grupo con el administrador de la organización', [
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $record;
    }

    /**
     * Ensure the Drive folder exists for a container inside the group folder.
     */
    public function ensureContainerFolder(Group $group, MeetingContentContainer $container): array
    {
        $group->loadMissing('organization');
        $organization = $group->organization;

        if (!$organization) {
            throw new \RuntimeException('El grupo no está asociado a ninguna organización.');
        }

        $this->initDrive($organization);

        $groupFolder = $this->ensureGroupFolder($group);

        if (!empty($container->drive_folder_id)) {
            return [
                'id' => $container->drive_folder_id,
                'metadata' => $container->metadata ?? [
                    'group_drive_folder_id' => $groupFolder->id,
                    'parent_google_id' => $groupFolder->google_id,
                    'name' => $container->name,
                ],
            ];
        }

        $folderName = $container->name ?? ('Contenedor ' . $container->id);

        $folderId = $this->drive->createFolder($folderName, $groupFolder->google_id);

        $serviceEmail = config('services.google.service_account_email');
        if ($serviceEmail) {
            try {
                $this->drive->shareFolder($folderId, $serviceEmail);
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir la carpeta del contenedor con la service account', [
                    'container_id' => $container->id,
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($adminEmail = optional($organization->admin)->email) {
            try {
                $this->drive->shareItem($folderId, $adminEmail, 'writer');
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir la carpeta del contenedor con el administrador de la organización', [
                    'container_id' => $container->id,
                    'group_id' => $group->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $metadata = [
            'group_drive_folder_id' => $groupFolder->id,
            'parent_google_id' => $groupFolder->google_id,
            'name' => $folderName,
        ];

        return [
            'id' => $folderId,
            'metadata' => $metadata,
        ];
    }

    /**
     * Rename the Drive folder associated with the group if necessary.
     */
    public function renameGroupFolder(Group $group, string $newName): void
    {
        $folder = $this->ensureGroupFolder($group);
        if ($folder->name === $newName) {
            return;
        }

        $this->initDrive($group->organization);

        try {
            $this->drive->renameFile($folder->google_id, $newName);
            $folder->update(['name' => $newName]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo renombrar la carpeta del grupo en Drive', [
                'group_id' => $group->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

