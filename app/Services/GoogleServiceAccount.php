<?php

namespace App\Services;

use App\Models\PendingFolder;
use App\Models\User;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleServiceAccount
{
    protected Client $client;
    protected Drive $drive;
    protected static bool $impersonationDisabled = false;

    public function __construct()
    {
        $this->client = new Client();
        // Read from config to work correctly with cached configuration
        $rawJsonPath = (string) config('services.google.service_account_json');
        $jsonPath = trim($rawJsonPath);
        $jsonPath = trim($jsonPath, "\"'");

        $resolvedFrom = [];

        // Detect absolute paths across Linux/Windows.
        // - Unix: /path
        // - Windows: C:\path or C:/path
        // - UNC: \\server\share
        $isAbsolute = false;
        if ($jsonPath !== '') {
            $isAbsolute = Str::startsWith($jsonPath, ['/', '\\'])
                || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $jsonPath)
                || Str::startsWith($jsonPath, ['\\\\\\\\', '//']);

            // Normalize separators for the current OS
            $jsonPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jsonPath);
        }

        if ($jsonPath !== '' && $isAbsolute) {
            $resolvedFrom[] = $jsonPath;
            $jsonPath = realpath($jsonPath) ?: $jsonPath;
        }

        if ($jsonPath !== '' && ! $isAbsolute) {
            // Interpret as relative path, try base_path then storage_path
            $baseCandidate = base_path($jsonPath);
            $storageCandidate = storage_path($jsonPath);
            $resolvedFrom[] = $baseCandidate;
            $resolvedFrom[] = $storageCandidate;

            $jsonPath = file_exists($baseCandidate) ? $baseCandidate : $storageCandidate;
            $jsonPath = realpath($jsonPath) ?: $jsonPath;
        }

        Log::debug('Service Account JSON Path', [
            'raw' => $rawJsonPath,
            'trimmed' => $jsonPath,
            'is_absolute' => $isAbsolute,
            'candidates' => $resolvedFrom,
            'exists' => $jsonPath !== '' ? file_exists($jsonPath) : false,
        ]);

        if ($jsonPath === '' || !file_exists($jsonPath)) {
            $hint = "Set GOOGLE_APPLICATION_CREDENTIALS to the full path of your service account JSON file (Windows example: C:\\keys\\service-account.json).";
            throw new RuntimeException("Service account JSON path is invalid. raw=\"{$rawJsonPath}\" resolved=\"{$jsonPath}\". {$hint}");
        }

        $this->client->setAuthConfig($jsonPath);
        $this->client->setScopes([Drive::DRIVE]);
        $this->drive = new Drive($this->client);
    }

    public function impersonate(?string $email): void
    {
        if ($email === null) {
            $this->client->setSubject(null);
            return;
        }
        if (self::$impersonationDisabled) {
            Log::debug('Impersonation skipped (disabled flag set)', ['email' => $email]);
            return; // Evitar reintentos costosos
        }
        try {
            $this->client->setSubject($email);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'unauthorized_client')) {
                self::$impersonationDisabled = true;
                Log::warning('Disabling impersonation after unauthorized_client', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                return; // No relanzamos para permitir fallback
            }
            throw $e;
        }
    }

    public static function impersonationDisabled(): bool
    {
        return self::$impersonationDisabled;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getDrive(): Drive
    {
        return $this->drive;
    }

    /**
     * Obtiene información detallada de un archivo usando la Service Account
     */
    public function getFileInfo(string $fileId): DriveFile
    {
        return $this->drive->files->get($fileId, [
            'fields' => 'id,name,parents,mimeType,size,createdTime,modifiedTime',
            'supportsAllDrives' => true,
        ]);
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
        Log::info('GoogleServiceAccount.createFolder: start', [
            'name' => $name,
            'parentId' => $parentId,
        ]);
        $fileMetadata = new DriveFile([
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId) {
            $fileMetadata->setParents([$parentId]);
        }

        $folder = $this->drive->files->create($fileMetadata, [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);
        Log::info('GoogleServiceAccount.createFolder: created', [
            'name' => $name,
            'parentId' => $parentId,
            'id' => $folder->getId(),
        ]);
        return $folder->getId();
    }

    public function shareFolder(string $folderId, string $email): void
    {
        $permission = new Permission([
            'type'         => 'user',
            'role'         => 'writer',
            'emailAddress' => $email,
        ]);

        $this->drive->permissions->create(
            $folderId,
            $permission,
            [
                'sendNotificationEmail' => false,
                'supportsAllDrives' => true,
            ]
        );
    }

    /**
     * Shares any file/folder with a user (impersonation-friendly). Defaults to reader.
     */
    public function shareItem(string $itemId, string $email, string $role = 'reader'): void
    {
        $permission = new Permission([
            'type'         => 'user',
            'role'         => $role,
            'emailAddress' => $email,
        ]);

        $this->drive->permissions->create(
            $itemId,
            $permission,
            [
                'sendNotificationEmail' => false,
                'supportsAllDrives' => true,
            ]
        );
    }

    public function getOrCreatePendingFolder(User $user): array
    {
        $pendingFolder = PendingFolder::where('username', $user->username)->first();

        if ($pendingFolder) {
            return [
                'id'      => $pendingFolder->google_id,
                'created' => false,
            ];
        }

        // Impersonar al usuario para crear la carpeta en su Drive
        $this->impersonate($user->email);
        $name = 'audio-pendiente-' . $user->username;
        $googleId = $this->createFolder($name);

        PendingFolder::updateOrCreate(
            ['username' => $user->username],
            ['google_id' => $googleId, 'name' => $name]
        );

        return [
            'id'      => $googleId,
            'created' => true,
            'message' => 'Pending folder created',
        ];
    }

    public function deleteFile(string $id): void
    {
        $this->drive->files->delete($id, [
            'supportsAllDrives' => true,
        ]);
    }

    public function uploadFile(
        string $name,
        string $mimeType,
        string $parentId,
        $contentsOrPath
    ): string {
        Log::info('GoogleServiceAccount.uploadFile: start', [
            'name' => $name,
            'mimeType' => $mimeType,
            'parentId' => $parentId,
            'contents_type' => is_resource($contentsOrPath) ? 'resource' : (is_string($contentsOrPath) && is_file($contentsOrPath) ? 'file_path' : gettype($contentsOrPath)),
        ]);
        $fileMetadata = new DriveFile([
            'name'    => $name,
            'parents' => [$parentId],
        ]);



        if (is_resource($contentsOrPath)) {
            $data = stream_get_contents($contentsOrPath);
            fclose($contentsOrPath);
        } elseif (is_string($contentsOrPath) && is_file($contentsOrPath)) {
            $data = file_get_contents($contentsOrPath);
        } else {
            $data = $contentsOrPath;
        }

        $file = $this->drive->files->create($fileMetadata, [
            'data'       => $data,
            'mimeType'   => $mimeType,
            'uploadType' => 'media',
            'fields'     => 'id',
            'supportsAllDrives' => true,
        ]);

        if (! $file->id) {
            throw new RuntimeException('No se obtuvo ID al subir el archivo.');
        }

        Log::info('GoogleServiceAccount.uploadFile: created', [
            'name' => $name,
            'mimeType' => $mimeType,
            'parentId' => $parentId,
            'id' => $file->id,
        ]);
        return $file->id;
    }

    public function getFileLink(string $fileId): string
    {
        $file = $this->drive->files->get($fileId, [
            'fields' => 'webViewLink',
            'supportsAllDrives' => true,
        ]);

        if (empty($file->webViewLink)) {
            throw new RuntimeException("No se pudo obtener webViewLink para el archivo $fileId");
        }

        return $this->normalizeDriveUrl($file->webViewLink);
    }

    private function normalizeDriveUrl(string $url): string
    {
        if (preg_match('/https:\/\/drive\.google\.com\/file\/d\/([^\/]+)\/view/', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }
        return $url;
    }

    /**
     * Descarga el contenido de un archivo desde Google Drive
     */
    public function downloadFile(string $fileId): string
    {
        try {
            $response = $this->drive->files->get($fileId, [
                'alt' => 'media'
            ]);

            if ($response instanceof \GuzzleHttp\Psr7\Response) {
                return $response->getBody()->getContents();
            }

            return (string) $response;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // Permission/auth errors are expected in some flows; log as warning to reduce noise
            $level = (str_contains($msg, 'unauthorized') || str_contains($msg, 'PERMISSION_DENIED') || str_contains($msg, 'forbidden')) ? 'warning' : 'error';
            Log::{$level}('Error downloading file from Google Drive', [
                'file_id' => $fileId,
                'error' => $msg
            ]);
            throw new RuntimeException('Error al descargar archivo: ' . $e->getMessage());
        }
    }

    /**
     * Mueve un archivo a una nueva carpeta y opcionalmente lo renombra
     */
    public function moveAndRenameFile(string $fileId, string $newParentId, string $newName = null): string
    {
        try {
            // Obtener información actual del archivo
            $file = $this->drive->files->get($fileId, [
                'fields' => 'parents,name'
            ]);

            $currentParents = $file->getParents();

            // Preparar los datos para la actualización
            $updateData = [];
            $options = [];

            // Si se proporciona un nuevo nombre
            if ($newName) {
                $updateData['name'] = $newName;
            }

            // Configurar el cambio de carpeta padre
            if ($currentParents) {
                $options['removeParents'] = implode(',', $currentParents);
            }
            $options['addParents'] = $newParentId;
            $options['fields'] = 'id,name,parents';

            // Crear objeto DriveFile con los nuevos datos
            $fileMetadata = new DriveFile($updateData);

            // Actualizar el archivo
            $updatedFile = $this->drive->files->update($fileId, $fileMetadata, $options);

            Log::info('File moved and renamed successfully', [
                'file_id' => $fileId,
                'new_parent' => $newParentId,
                'new_name' => $newName,
                'updated_file_id' => $updatedFile->getId()
            ]);

            return $updatedFile->getId();

        } catch (\Exception $e) {
            Log::error('Error moving and renaming file', [
                'file_id' => $fileId,
                'new_parent' => $newParentId,
                'new_name' => $newName,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Error al mover y renombrar archivo: ' . $e->getMessage());
        }
    }

    /**
     * Copia un archivo a una nueva ubicación con un nuevo nombre
     */
    public function copyFile(string $fileId, string $newParentId, string $newName): string
    {
        try {
            $copiedFile = new DriveFile([
                'name' => $newName,
                'parents' => [$newParentId]
            ]);

            $result = $this->drive->files->copy($fileId, $copiedFile, [
                'fields' => 'id,name,parents'
            ]);

            return $result->getId();

        } catch (\Exception $e) {
            Log::error('Error copying file', [
                'file_id' => $fileId,
                'new_parent' => $newParentId,
                'new_name' => $newName,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Error al copiar archivo: ' . $e->getMessage());
        }
    }
}
