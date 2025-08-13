<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Calendar;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;

use RuntimeException;

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

        // Configurar API Key si está disponible
        $apiKey = config('services.google.api_key');
        if ($apiKey) {
            $this->client->setDeveloperKey($apiKey);
            Log::info('GoogleDriveService: API Key configured', [
                'has_api_key' => true,
                'api_key_length' => strlen($apiKey)
            ]);
        } else {
            Log::warning('GoogleDriveService: No API Key configured', [
                'has_api_key' => false
            ]);
        }

        $this->client->setScopes([Drive::DRIVE, Calendar::CALENDAR]);
        $this->client->setAccessType('offline');

        $this->drive = new Drive($this->client);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setAccessToken(array|string $accessToken): void
    {
        // Google Client acepta array o JSON. Si recibimos un string plano,
        // lo convertimos al formato esperado.
        if (is_string($accessToken)) {
            $decoded = json_decode($accessToken, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $accessToken = $decoded;
            } else {
                $accessToken = ['access_token' => $accessToken];
            }
        }

        Log::info('GoogleDriveService: setAccessToken called', [
            'type' => gettype($accessToken),
            'has_access_token' => isset($accessToken['access_token']),
            'token_preview' => is_array($accessToken) && isset($accessToken['access_token'])
                ? substr($accessToken['access_token'], 0, 20) . '...'
                : 'N/A'
        ]);

        $this->client->setAccessToken($accessToken);

        // Verificar que el token se estableció
        $currentToken = $this->client->getAccessToken();
        Log::info('GoogleDriveService: Token status after setting', [
            'has_token' => !empty($currentToken),
            'is_expired' => $this->client->isAccessTokenExpired()
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        $this->client->refreshToken($refreshToken);
        $newToken = $this->client->getAccessToken();
        if (is_string($newToken)) {
            $parsed = json_decode($newToken, true) ?: ['access_token' => $newToken];
            $newToken = $parsed;
        }
        // Asegurar que el cliente queda con el nuevo token activo
        $this->client->setAccessToken($newToken);
        return $newToken;
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
        public function listSubfolders(string $parentId): array
    {
        $response = $this->drive->files->listFiles([
            'q'      => sprintf(
                "mimeType='application/vnd.google-apps.folder' and '%s' in parents and trashed=false",
                $parentId
            ),
            'fields' => 'files(id,name)'
        ]);

        return $response->getFiles();
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
        $this->drive->files->delete($id, [
            'supportsAllDrives' => true,
        ]);
    }
        /**
     * Sube un archivo a Google Drive y devuelve su ID.
     */
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
            'data'         => $contents,
            'mimeType'     => $mimeType,
            'uploadType'   => 'multipart',
            'fields'       => 'id',
        ]);

        if (! $file->id) {
            throw new RuntimeException('No se obtuvo ID al subir el archivo.');
        }

        return $file->id;
    }

    /**
     * Obtiene el enlace webViewLink (para ver/compartir) de un archivo existente.
     */
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

    /**
     * Descarga el contenido de un archivo de Google Drive
     */
    public function downloadFileContent(string $fileId): string
    {
        try {
            // Usar downloadContent en lugar de get con alt=media
            $response = $this->drive->files->get($fileId, [
                'alt' => 'media',
                'supportsAllDrives' => true,
            ]);

            // Log para debug
            Log::info('Google Drive API response type', [
                'type' => gettype($response),
                'class' => is_object($response) ? get_class($response) : 'not_object'
            ]);

            // Manejar GuzzleHttp\Psr7\Response
            if ($response instanceof Response) {
                $body = $response->getBody();
                return $body->getContents();
            }

            // Si es otra cosa, intentar convertir a string
            return (string) $response;

        } catch (\Exception $e) {
            Log::error('Error downloading file from Google Drive', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to download file from Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el nombre de un archivo en Drive
     */
    public function updateFileName(string $fileId, string $newName): void
    {
        $fileMetadata = new DriveFile([
            'name' => $newName
        ]);

        $this->drive->files->update($fileId, $fileMetadata, [
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * Busca archivos en Drive por nombre
     */
    public function searchFiles(string $query, ?string $parentId = null): array
    {
        $searchQuery = "name contains '$query' and trashed=false";

        if ($parentId) {
            $searchQuery .= " and '$parentId' in parents";
        }

        $response = $this->drive->files->listFiles([
            'q' => $searchQuery,
            'fields' => 'files(id,name,parents,mimeType)'
        ]);

        return $response->getFiles();
    }

    /**
     * Obtiene información detallada de un archivo
     */
    public function getFileInfo(string $fileId): DriveFile
    {
        return $this->drive->files->get($fileId, [
            'fields' => 'id,name,parents,mimeType,size,createdTime,modifiedTime',
            'supportsAllDrives' => true,
        ]);
    }

}
