<?php

namespace App\Services;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class GoogleDriveService
{
    protected Google_Client $client;
    protected Google_Service_Drive $drive;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(config('drive.credentials_path'));
        $this->client->setScopes([Google_Service_Drive::DRIVE]);
        $this->client->setAccessType('offline');

        $this->drive = new Google_Service_Drive($this->client);
    }

    public function getClient(): Google_Client
    {
        return $this->client;
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
        $metadata = new Google_Service_Drive_DriveFile([
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if ($parentId) {
            $metadata->setParents([$parentId]);
        }
        $folder = $this->drive->files->create($metadata, ['fields' => 'id']);

        return $folder->id;
    }

    public function listFolders(string $query = null): array
    {
        $params = [
            'q'      => $query ?? "mimeType='application/vnd.google-apps.folder'",
            'fields' => 'files(id,name)',
        ];
        $results = $this->drive->files->listFiles($params);
        return $results->getFiles();
    }
}
