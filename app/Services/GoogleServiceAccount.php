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
        $jsonPath = env('GOOGLE_APPLICATION_CREDENTIALS');

        if ($jsonPath) {
            $jsonPath = str_replace('\\', DIRECTORY_SEPARATOR, $jsonPath);
            $jsonPath = realpath($jsonPath) ?: $jsonPath;
        }

        if ($jsonPath && ! Str::startsWith($jsonPath, ['/', '\\'])) {
            $basePath = base_path($jsonPath);
            $jsonPath = file_exists($basePath) ? $basePath : storage_path($jsonPath);
            $jsonPath = realpath($jsonPath) ?: $jsonPath;
        }

        if (! $jsonPath || ! file_exists($jsonPath)) {
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
        $contentsOrPath
    ): string {
        $fileMetadata = new DriveFile([
            'name'    => $name,
            'parents' => [$parentId],
        ]);



        // Si es un recurso, usar uploadType 'media', si no, 'multipart'
        if (is_resource($contentsOrPath)) {
            $data = $contentsOrPath;
            $uploadType = 'media';
        } elseif (is_string($contentsOrPath) && is_file($contentsOrPath)) {
            $data = fopen($contentsOrPath, 'rb');
            $uploadType = 'media';
        } else {
            $data = $contentsOrPath;
            $uploadType = 'multipart';
        }

        $file = $this->drive->files->create($fileMetadata, [
            'data'       => $data,
            'mimeType'   => $mimeType,
            'uploadType' => $uploadType,
            'fields'     => 'id',
        ]);

        if (is_resource($data)) {
            fclose($data);
        }

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
