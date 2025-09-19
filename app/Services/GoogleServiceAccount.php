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

    public function __construct()
    {
        $this->client = new Client();
        // Read from config to work correctly with cached configuration
        $jsonPath = (string) config('services.google.service_account_json');

        if ($jsonPath) {
            $jsonPath = str_replace('\\', DIRECTORY_SEPARATOR, $jsonPath);
            $jsonPath = realpath($jsonPath) ?: $jsonPath;
        }

        if ($jsonPath && ! Str::startsWith($jsonPath, ['/', '\\'])) {
            $basePath = base_path($jsonPath);
            $jsonPath = file_exists($basePath) ? $basePath : storage_path($jsonPath);
            $jsonPath = realpath($jsonPath) ?: $jsonPath;
        }

        Log::debug('Service Account JSON Path', ['path' => $jsonPath, 'exists' => $jsonPath ? file_exists($jsonPath) : false]);
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException('Service account JSON path is invalid');
        }

        $this->client->setAuthConfig($jsonPath);
        $this->client->setScopes([Drive::DRIVE]);
        $this->drive = new Drive($this->client);
    }

    public function impersonate(string $email): void
    {
        $this->client->setSubject($email);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getDrive(): Drive
    {
        return $this->drive;
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
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
            Log::error('Error downloading file from Google Drive', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
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
