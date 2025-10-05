<?php

namespace App\Traits;

use App\Models\Folder;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\Subfolder;
use App\Models\GoogleToken;
use App\Models\User;
use App\Services\GoogleServiceAccount;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;

trait EnsuresStandardSubfolders
{
    /**
     * Ensure the four standard subfolders exist under the provided root folder:
     *  - Audios
     *  - Transcripciones
     *  - Audios Pospuestos
     *  - Documentos
     * Works for both personal and organization drives, creating missing folders in Google Drive
     * and persisting them in the corresponding DB tables. Returns an associative array with the
     * Subfolder / OrganizationSubfolder models: ['audio' => ..., 'transcription' => ..., 'pending' => ...]
     */
    protected function ensureStandardSubfolders($rootFolder, bool $useOrgDrive, GoogleServiceAccount $serviceAccount): array
    {
        $parentId = $rootFolder->google_id ?? null;
        if (empty($parentId)) {
            Log::error('ensureStandardSubfolders: root folder has no google_id', [
                'useOrgDrive' => $useOrgDrive,
                'root_model_id' => $rootFolder->id ?? null,
            ]);
            return [];
        }
        $names = [
            'audio'         => 'Audios',
            'transcription' => 'Transcripciones',
            'pending'       => 'Audios Pospuestos',
            'documents'     => 'Documentos',
        ];

        $result = [];
        $serviceEmail = config('services.google.service_account_email');
        $ownerEmail = $this->resolveRootOwnerEmail($rootFolder, $useOrgDrive);
        $impersonationActive = false;

        try {
            foreach ($names as $key => $folderName) {
                try {
                    if ($useOrgDrive) {
                        $model = OrganizationSubfolder::where('organization_folder_id', $rootFolder->id)
                            ->where('name', $folderName)
                            ->first();
                        if (!$model) {
                            $existingId = $this->findExistingSubfolderIdInDrive($serviceAccount, $parentId, $folderName);
                            if ($existingId) {
                                $model = OrganizationSubfolder::firstOrCreate([
                                    'organization_folder_id' => $rootFolder->id,
                                    'google_id'              => $existingId,
                                ], ['name' => $folderName]);
                                try {
                                    if ($serviceEmail) {
                                        $serviceAccount->shareFolder($existingId, $serviceEmail);
                                    }
                                } catch (\Throwable $e) {
                                }
                                $result[$key] = $model;
                                continue;
                            }
                            try {
                                $googleId = $serviceAccount->createFolder($folderName, $parentId);
                            } catch (\Throwable $e) {
                                Log::warning('Org subfolder create with SA failed, trying impersonation', [
                                    'folder' => $folderName,
                                    'parent' => $parentId,
                                    'error'  => $e->getMessage(),
                                ]);
                                if ($ownerEmail) {
                                    try {
                                        $serviceAccount->impersonate($ownerEmail);
                                        $impersonationActive = true;
                                        $googleId = $serviceAccount->createFolder($folderName, $parentId);
                                    } catch (\Throwable $e2) {
                                        throw $e2;
                                    }
                                } else {
                                    throw $e;
                                }
                            }
                            $model = OrganizationSubfolder::create([
                                'organization_folder_id' => $rootFolder->id,
                                'google_id'              => $googleId,
                                'name'                   => $folderName,
                            ]);
                            try {
                                if ($serviceEmail) {
                                    $serviceAccount->shareFolder($googleId, $serviceEmail);
                                }
                            } catch (\Throwable $e) {
                            }
                        }
                    } else {
                        $model = Subfolder::where('folder_id', $rootFolder->id)
                            ->where('name', $folderName)
                            ->first();
                        if (!$model) {
                            $impersonatedForFolder = false;

                            try {
                                $existingId = $this->findExistingSubfolderIdInDrive($serviceAccount, $parentId, $folderName);
                                if ($existingId) {
                                    $model = Subfolder::firstOrCreate([
                                        'folder_id' => $rootFolder->id,
                                        'google_id' => $existingId,
                                    ], ['name' => $folderName]);
                                    try {
                                        if ($serviceEmail) {
                                            $serviceAccount->shareFolder($existingId, $serviceEmail);
                                        }
                                    } catch (\Throwable $e) {
                                    }
                                    $result[$key] = $model;
                                    continue;
                                }
                                try {
                                    $googleId = $serviceAccount->createFolder($folderName, $parentId);
                                } catch (\Throwable $createException) {
                                    $requiresImpersonation = $this->shouldRetryWithImpersonation($createException);
                                    if ($requiresImpersonation && $ownerEmail) {
                                        Log::notice('Personal subfolder creation requires impersonation', [
                                            'folder'      => $folderName,
                                            'parent'      => $parentId,
                                            'ownerEmail'  => $ownerEmail,
                                            'error'       => $createException->getMessage(),
                                        ]);

                                        $serviceAccount->impersonate($ownerEmail);
                                        $impersonationActive = true;
                                        $impersonatedForFolder = true;
                                        $googleId = $serviceAccount->createFolder($folderName, $parentId);
                                    } elseif ($requiresImpersonation) {
                                        Log::error('Personal subfolder creation failed: impersonation unavailable', [
                                            'folder'     => $folderName,
                                            'parent'     => $parentId,
                                            'ownerEmail' => $ownerEmail,
                                            'error'      => $createException->getMessage(),
                                        ]);

                                        throw $createException;
                                    } else {
                                        throw $createException;
                                    }
                                }

                                $model = Subfolder::create([
                                    'folder_id' => $rootFolder->id,
                                    'google_id' => $googleId,
                                    'name'      => $folderName,
                                ]);
                                try {
                                    if ($serviceEmail) {
                                        $serviceAccount->shareFolder($googleId, $serviceEmail);
                                    }
                                } catch (\Throwable $e) {
                                }
                            } finally {
                                if (isset($impersonatedForFolder) && $impersonatedForFolder) {
                                    $this->resetImpersonation($serviceAccount, $impersonationActive);
                                }
                            }
                        }
                    }
                    $result[$key] = $model;
                } catch (\Throwable $e) {
                    Log::warning('ensureStandardSubfolders failure', [
                        'folder' => $folderName,
                        'requires_impersonation' => $this->shouldRetryWithImpersonation($e),
                        'impersonation_active' => $impersonationActive,
                        'ownerEmailAvailable' => (bool) $ownerEmail,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            if ($impersonationActive) {
                $this->resetImpersonation($serviceAccount, $impersonationActive);
            }
        }

        if (!$ownerEmail) {
            Log::debug('ensureStandardSubfolders owner email not found', [
                'useOrgDrive' => $useOrgDrive,
                'rootFolderId' => $rootFolder->id ?? null,
            ]);
        }

        return $result;
    }

    protected function findExistingSubfolderIdInDrive(GoogleServiceAccount $serviceAccount, string $parentId, string $name): ?string
    {
        try {
            $drive = $serviceAccount->getDrive();
            $results = $drive->files->listFiles([
                'q' => sprintf(
                    "mimeType='application/vnd.google-apps.folder' and name='%s' and '%s' in parents and trashed=false",
                    addslashes($name),
                    $parentId
                ),
                'fields' => 'files(id,name,parents)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);
            $files = $results->getFiles();
            if (!empty($files)) {
                return $files[0]->getId();
            }
        } catch (\Throwable $e) {
            Log::debug('findExistingSubfolderIdInDrive failed', [
                'parentId' => $parentId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
        }
        return null;
    }

    protected function shouldRetryWithImpersonation(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage() ?? '');
        $keywords = ['permission', 'insufficient', 'forbidden', 'invalid_grant', 'access denied'];

        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        if ($exception instanceof GoogleServiceException && method_exists($exception, 'getErrors')) {
            $errors = $exception->getErrors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $reason = strtolower($error['reason'] ?? '');
                    if (in_array($reason, ['invalid_grant', 'insufficientpermissions', 'forbidden', 'accessdenied'], true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function resetImpersonation(GoogleServiceAccount $serviceAccount, bool &$impersonationActive): void
    {
        if (!$impersonationActive) {
            return;
        }

        try {
            $serviceAccount->impersonate(null);
        } catch (\Throwable $e) {
            Log::debug('Failed to reset impersonation after ensuring subfolders', [
                'error' => $e->getMessage(),
            ]);
        }

        $impersonationActive = false;
    }

    protected function resolveRootOwnerEmail($rootFolder, bool $useOrgDrive): ?string
    {
        if ($useOrgDrive && $rootFolder instanceof OrganizationFolder) {
            $rootFolder->loadMissing(['organization.admin', 'googleToken']);
            $token = $rootFolder->googleToken;
            if ($token) {
                $email = $token->impersonate_email
                    ?? $token->connected_email
                    ?? $token->owner_email
                    ?? $token->email
                    ?? null;
                if ($email) {
                    return $email;
                }
            }

            return optional(optional($rootFolder->organization)->admin)->email;
        }

        if (!$useOrgDrive && $rootFolder instanceof Folder) {
            $token = GoogleToken::find($rootFolder->google_token_id);
            if ($token) {
                return User::where('username', $token->username)->value('email');
            }
        }

        return null;
    }
}
