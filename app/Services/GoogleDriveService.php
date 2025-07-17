<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveService
{
    protected Client $client;
    protected Drive  $drive;

    public function __construct()
    {
        $credentialsPath = config('drive.credentials_path');
        if (! file_exists($credentialsPath)) {
            throw new \RuntimeException("Credenciales de Google no encontradas en: $credentialsPath");
        }

        $this->client = new Client();
        $this->client->setAuthConfig($credentialsPath);
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
}
