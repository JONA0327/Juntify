<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleServiceAccount
{
    protected Client $client;
    protected Drive $drive;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setAuthConfig(config('services.google.service_account_json'));
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
            'name' => $name,
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
}
