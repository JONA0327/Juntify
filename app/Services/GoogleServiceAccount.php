<?php

namespace App\Services;

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
        $jsonPath = config('services.google.service_account_json');

        // Si la ruta no es absoluta (ni Unix ni Windows), la convertimos
        if (
            $jsonPath
            && !Str::startsWith($jsonPath, '/')
            // ahora usamos '#' como delimitador y escapamos '\\' correctamente
            && !preg_match('#^[A-Za-z]:(\\\\|/)#', $jsonPath)
        ) {
            $jsonPath = base_path($jsonPath);
        }

        if (!$jsonPath || !is_file($jsonPath) || !is_readable($jsonPath)) {
            Log::error('Invalid Google service account JSON path', ['path' => $jsonPath]);
            throw new RuntimeException('Service account JSON path is invalid');
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
            ['sendNotificationEmail' => false]
        );
    }

    public function deleteFile(string $id): void
    {
        $this->drive->files->delete($id);
    }

    public function uploadFile(
        string $name,
        string $mimeType,
        string $parentId,
        string $contents
    ): string {
        $fileMetadata = new DriveFile([
            'name'    => $name,
            'parents' => [$parentId],
        ]);

        $file = $this->drive->files->create($fileMetadata, [
            'data'       => $contents,
            'mimeType'   => $mimeType,
            'uploadType' => 'multipart',
            'fields'     => 'id',
        ]);

        if (! $file->id) {
            throw new RuntimeException('No se obtuvo ID al subir el archivo.');
        }

        return $file->id;
    }

    public function getFileLink(string $fileId): string
    {
        $file = $this->drive->files->get($fileId, [
            'fields' => 'webViewLink'
        ]);

        if (empty($file->webViewLink)) {
            throw new RuntimeException("No se pudo obtener webViewLink para el archivo $fileId");
        }

        return $file->webViewLink;
    }
}
