<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Exception as GoogleServiceException;

class GoogleDriveService
{
    protected Client $client;
    protected Drive  $drive;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect'));
        $this->client->setScopes([Drive::DRIVE]);
        $this->client->setAccessType('offline');

        $this->drive = new Drive($this->client);
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

    /**
     * @param string|null $query
     * @return DriveFile[]
     */
    public function listFolders(?string $query = null): array
    {
        $q = $query ?? "mimeType='application/vnd.google-apps.folder'";
        $results = $this->drive->files->listFiles([
            'q'      => $q,
            'fields' => 'files(id,name)',
        ]);

        return $results->getFiles();
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

    /**
     * @param string|resource $contents
     */
    public function uploadFile(string $name, string $mime, string $folderId, $contents): string
    {
        $fileMetadata = new DriveFile([
            'name'    => $name,
            'parents' => [$folderId],
        ]);

        try {
            $file = $this->drive->files->create($fileMetadata, [
                'data'       => $contents,
                'mimeType'   => $mime,
                'uploadType' => 'multipart',
                'fields'     => 'id',
            ]);
        } catch (GoogleServiceException $e) {
            throw new \RuntimeException('Google Drive upload failed: ' . $e->getMessage(), 0, $e);
        }

        return $file->getId();
    }

    public function getFileLink(string $fileId): string
    {
        try {
            $file = $this->drive->files->get($fileId, [
                'fields' => 'webContentLink,webViewLink',
            ]);
        } catch (GoogleServiceException $e) {
            throw new \RuntimeException('Google Drive request failed: ' . $e->getMessage(), 0, $e);
        }

        $link = $file->getWebContentLink() ?: $file->getWebViewLink();

        if (!$link) {
            throw new \RuntimeException('No link available for the requested file.');
        }

        return $link;
    }

    public function deleteFile(string $id): void
    {
        $this->drive->files->delete($id);
    }
}
