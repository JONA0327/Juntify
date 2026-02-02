<?php

namespace App\Services;

use App\Exceptions\GoogleDriveFileException;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Exception as GoogleServiceException;
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
        
        // Deshabilitar el uso de archivos temporales para descargas
        $this->client->setDefer(false);

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

    public function createFolder(string $name, ?string $parentId = null, bool $autoShare = true): string
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

        $folderId = $folder->getId();

        // Compartir automáticamente con la cuenta de servicio si está configurada
        if ($autoShare) {
            $serviceEmail = config('services.google.service_account_email');
            if ($serviceEmail) {
                try {
                    $this->shareFolder($folderId, $serviceEmail);
                    Log::info('GoogleDriveService.createFolder: auto-shared with service account', [
                        'folderId' => $folderId,
                        'email' => $serviceEmail,
                    ]);
                } catch (\Throwable $e) {
                    // No falla si no se puede compartir, solo logueamos
                    Log::warning('GoogleDriveService.createFolder: failed to auto-share', [
                        'folderId' => $folderId,
                        'email' => $serviceEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $folderId;
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
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
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
            'fields' => 'files(id,name)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
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

        try {
            $this->drive->permissions->create(
                $folderId,
                $permission,
                [
                    'sendNotificationEmail' => false,
                    'supportsAllDrives' => true,
                ]
            );
        } catch (GoogleServiceException $e) {
            // Silenciar errores comunes de duplicado/permisos ya existentes
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'already') || str_contains($msg, 'duplicate')) {
                Log::info('shareFolder: permiso ya existente', [
                    'folder_id' => $folderId,
                    'email' => $email,
                ]);
                return;
            }
            throw $e;
        }
    }

    /**
     * Intenta asegurar que un folder esté compartido con la service account usando primero la service account
     * y si falla por permisos, reintenta con el token OAuth (cliente actual) – pensado para carpetas personales.
     * Devuelve true si queda compartido, false si no se pudo.
     */
    public function ensureSharedWithServiceAccount(string $folderId, string $serviceEmail): bool
    {
        try {
            $this->shareFolder($folderId, $serviceEmail);
            return true; // funcionó directamente
        } catch (GoogleServiceException $e) {
            $code = (int)$e->getCode();
            $msg  = strtolower($e->getMessage());
            if (!in_array($code, [403, 404]) && !str_contains($msg, 'not have permission')) {
                // Error distinto, re-lanzar
                throw $e;
            }
            Log::warning('ensureSharedWithServiceAccount: directo falló, intentando vía token usuario', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            // En este punto asumimos que el cliente ya está configurado con el token OAuth del usuario.
            try {
                $this->shareFolder($folderId, $serviceEmail);
                return true;
            } catch (GoogleServiceException $e2) {
                Log::error('ensureSharedWithServiceAccount: fallback OAuth también falló', [
                    'folder_id' => $folderId,
                    'error' => $e2->getMessage(),
                ]);
                return false;
            }
        }
    }

    /**
     * Shares any Drive item (file or folder) with a user. Defaults to reader access.
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
     * Devuelve un enlace de descarga (webContentLink) para un archivo si está disponible.
     */
    public function getWebContentLink(string $fileId): ?string
    {
        try {
            $file = $this->drive->files->get($fileId, [
                'fields' => 'webContentLink',
                'supportsAllDrives' => true,
            ]);
            $link = $file->getWebContentLink();
            return $link ? $this->normalizeDriveUrl($link) : null;
        } catch (GoogleServiceException $e) {
            Log::warning('getWebContentLink error', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return null;
        }
    }

    /**
     * Descarga el contenido de un archivo de Google Drive
     */
    public function downloadFileContent(string $fileId): ?string
    {
        try {
            Log::info('Attempting to download file content from Google Drive', [
                'file_id' => $fileId,
                'client_has_token' => $this->client->getAccessToken() !== null
            ]);

            // Obtener el token de acceso actual
            $token = $this->client->getAccessToken();
            $accessToken = is_array($token) ? $token['access_token'] : $token;

            // Usar Guzzle directamente para descargar el archivo
            $httpClient = new \GuzzleHttp\Client();
            $response = $httpClient->get("https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'stream' => false, // Importante: no usar streaming
            ]);

            $contents = $response->getBody()->getContents();

            // Verificar si el contenido parece ser HTML (error)
            if (strlen($contents) < 100 && strpos($contents, '<') !== false) {
                Log::error('Downloaded content appears to be HTML error page', [
                    'file_id' => $fileId,
                    'content_length' => strlen($contents),
                    'content_preview' => substr($contents, 0, 200)
                ]);
                return null;
            }

            Log::info('Content downloaded directly via Guzzle', [
                'file_id' => $fileId,
                'content_length' => strlen($contents),
                'status_code' => $response->getStatusCode()
            ]);

            return $contents;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Guzzle Request Exception during download', [
                'file_id' => $fileId,
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ]);
            return null;
        } catch (GoogleServiceException $e) {
            Log::error('Google Service Exception during download', [
                'file_id' => $fileId,
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);

            if ($e->getCode() === 404) {
                Log::warning('File not found on Google Drive', ['file_id' => $fileId]);
                return null;
            }

            if ($e->getCode() === 403) {
                Log::error('Permission denied for Google Drive file', [
                    'file_id' => $fileId,
                    'message' => $e->getMessage()
                ]);
                throw new GoogleDriveFileException($fileId, 403, 'Sin permisos para acceder al archivo', $e);
            }

            throw new GoogleDriveFileException($fileId, (int) $e->getCode(), $e->getMessage(), $e);
        } catch (\Throwable $e) {
            Log::error('Unexpected error during file download', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'class' => get_class($e)
            ]);

            throw $e;
        }
    }

    /**
     * Actualiza el contenido de un archivo en Drive y devuelve su webViewLink.
     */
    public function updateFileContent(string $fileId, string $mimeType, $data): string
    {
        $file = $this->drive->files->update($fileId, new DriveFile(), [
            'data' => $data,
            'mimeType' => $mimeType,
            'uploadType' => 'media',
            'supportsAllDrives' => true,
            'fields' => 'id,webViewLink'
        ]);

        if (empty($file->webViewLink)) {
            throw new RuntimeException("No se pudo obtener webViewLink para el archivo $fileId");
        }

        return $file->webViewLink;
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
            'fields' => 'files(id,name,parents,mimeType)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
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

    /**
     * Busca un archivo de audio dentro de una carpeta por título o ID de reunión
     */
    public function findAudioInFolder(string $folderId, string $meetingTitle, string $meetingId): ?array
    {
        $response = $this->drive->files->listFiles([
            'q' => sprintf("'%s' in parents and trashed=false", $folderId),
            'fields' => 'files(id,name,webContentLink)',
            'supportsAllDrives' => true,
        ]);

        foreach ($response->getFiles() as $file) {
            $name = $file->getName();
            if (
                preg_match('/^' . preg_quote($meetingTitle, '/') . '/i', $name) ||
                preg_match('/^' . preg_quote($meetingId, '/') . '/i', $name)
            ) {
                return [
                    'fileId' => $file->getId(),
                    'downloadUrl' => $this->normalizeDriveUrl($file->getWebContentLink()),
                ];
            }
        }

        return null;
    }

    /**
     * Renombra un archivo en Google Drive
     */
    public function renameFile(string $fileId, string $newName): DriveFile
    {
        try {
            $file = new DriveFile();
            $file->setName($newName);

            return $this->drive->files->update($fileId, $file, [
                'supportsAllDrives' => true,
                'fields' => 'id,name'
            ]);
        } catch (GoogleServiceException $e) {
            Log::error('Error renaming file in Google Drive', [
                'file_id' => $fileId,
                'new_name' => $newName,
                'error' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
            throw new GoogleDriveFileException(
                'Failed to rename file: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Elimina un archivo o carpeta en Google Drive.
     * Lanza excepción si falla la eliminación.
     */
    public function deleteFile(string $fileId): void
    {
        try {
            $this->drive->files->delete($fileId, [
                'supportsAllDrives' => true,
            ]);
            Log::info('GoogleDriveService: archivo eliminado exitosamente', [
                'file_id' => $fileId,
            ]);
        } catch (\Throwable $e) {
            Log::error('GoogleDriveService: fallo al borrar archivo/carpeta', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            // Propagar la excepción para que el controlador la maneje
            throw $e;
        }
    }

    /**
     * Elimina una carpeta de Google Drive
     *
     * @param string $folderId
     * @return void
     * @throws \Throwable
     */
    public function deleteFolder(string $folderId): void
    {
        try {
            // Verificar que la carpeta existe antes de intentar eliminarla
            $folderInfo = $this->getFileInfo($folderId);

            if (!$folderInfo || $folderInfo->getMimeType() !== 'application/vnd.google-apps.folder') {
                throw new \Exception("La carpeta con ID {$folderId} no existe o no es una carpeta válida");
            }

            Log::info('GoogleDriveService: intentando eliminar carpeta', [
                'folder_id' => $folderId,
                'folder_name' => $folderInfo->getName(),
            ]);

            // Intentar eliminar la carpeta directamente
            $this->drive->files->delete($folderId, [
                'supportsAllDrives' => true,
            ]);

            Log::info('GoogleDriveService: carpeta eliminada exitosamente', [
                'folder_id' => $folderId,
                'folder_name' => $folderInfo->getName(),
            ]);

        } catch (\Google_Service_Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            Log::error('GoogleDriveService: error de Google API al borrar carpeta', [
                'folder_id' => $folderId,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            // Si es error de permisos (403), intentar estrategias alternativas
            if ($errorCode === 403) {
                Log::warning('GoogleDriveService: error de permisos, intentando mover carpeta a papelera', [
                    'folder_id' => $folderId,
                ]);

                try {
                    // Intentar mover a papelera en lugar de eliminar permanentemente
                    $this->drive->files->update($folderId, new DriveFile([
                        'trashed' => true
                    ]), [
                        'supportsAllDrives' => true,
                    ]);

                    Log::info('GoogleDriveService: carpeta movida a papelera exitosamente', [
                        'folder_id' => $folderId,
                    ]);
                    return; // Éxito con método alternativo

                } catch (\Throwable $trashError) {
                    Log::error('GoogleDriveService: también falló mover a papelera', [
                        'folder_id' => $folderId,
                        'trash_error' => $trashError->getMessage(),
                    ]);
                }
            }

            // Si no se pudo resolver, propagar el error original
            throw $e;

        } catch (\Throwable $e) {
            Log::error('GoogleDriveService: fallo general al borrar carpeta', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            // Propagar la excepción para que el controlador la maneje
            throw $e;
        }
    }

    /**
     * Elimina una carpeta de forma robusta usando múltiples estrategias
     * Similar al método deleteDriveFileResilient del MeetingController
     *
     * @param string $folderId
     * @param string $userEmail
     * @return bool
     */
    public function deleteFolderResilient(string $folderId, string $userEmail): bool
    {
        Log::info('GoogleDriveService: iniciando eliminación robusta de carpeta', [
            'folder_id' => $folderId,
            'user_email' => $userEmail,
        ]);

        // La mayoría de carpetas son creadas por Service Account, entonces empezar por ahí
        // Primero, intentar con Service Account sin impersonate (más directo)
        try {
            $sa = app(\App\Services\GoogleServiceAccount::class);
            $sa->deleteFile($folderId);
            Log::info('GoogleDriveService: carpeta eliminada con service account directo', ['folder_id' => $folderId]);
            return true;
        } catch (\Exception $e) {
            Log::warning('GoogleDriveService: fallo con service account directo, intentando impersonation', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        // Segundo, intentar con Service Account impersonando al usuario
        try {
            $sa = app(\App\Services\GoogleServiceAccount::class);
            $sa->impersonate($userEmail);
            $sa->deleteFile($folderId); // Las carpetas también se eliminan con deleteFile
            Log::info('GoogleDriveService: carpeta eliminada con service account impersonando', ['folder_id' => $folderId]);
            return true;
        } catch (\Exception $e) {
            Log::warning('GoogleDriveService: fallo con service account impersonando, intentando token de usuario', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        // Tercero, intentar con el token del usuario (para carpetas creadas por el usuario)
        try {
            $this->deleteFolder($folderId);
            Log::info('GoogleDriveService: carpeta eliminada con token de usuario', ['folder_id' => $folderId]);
            return true;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: fallo con token de usuario también', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        Log::error('GoogleDriveService: no se pudo eliminar la carpeta con ninguna estrategia', [
            'folder_id' => $folderId,
        ]);
        return false;
    }

    /**
     * Elimina un archivo de forma robusta usando múltiples estrategias
     *
     * @param string $fileId
     * @param string $userEmail
     * @return bool
     */
    public function deleteFileResilient(string $fileId, string $userEmail): bool
    {
        Log::info('GoogleDriveService: iniciando eliminación robusta de archivo', [
            'file_id' => $fileId,
            'user_email' => $userEmail,
        ]);

        // La mayoría de archivos son creados por Service Account, entonces empezar por ahí
        // Primero, intentar con Service Account sin impersonate (más directo)
        try {
            $sa = app(\App\Services\GoogleServiceAccount::class);
            $sa->deleteFile($fileId);
            Log::info('GoogleDriveService: archivo eliminado con service account directo', ['file_id' => $fileId]);
            return true;
        } catch (\Exception $e) {
            Log::warning('GoogleDriveService: fallo con service account directo, intentando impersonation', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        // Segundo, intentar con Service Account impersonando al usuario
        try {
            $sa = app(\App\Services\GoogleServiceAccount::class);
            $sa->impersonate($userEmail);
            $sa->deleteFile($fileId);
            Log::info('GoogleDriveService: archivo eliminado con service account impersonando', ['file_id' => $fileId]);
            return true;
        } catch (\Exception $e) {
            Log::warning('GoogleDriveService: fallo con service account impersonando, intentando token de usuario', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        // Tercero, intentar con el token del usuario (para archivos creados por el usuario)
        try {
            $this->deleteFile($fileId);
            Log::info('GoogleDriveService: archivo eliminado con token de usuario', ['file_id' => $fileId]);
            return true;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService: fallo con token de usuario también', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        Log::error('GoogleDriveService: no se pudo eliminar el archivo con ninguna estrategia', [
            'file_id' => $fileId,
        ]);
        return false;
    }

    /**
     * Verifica si un error es de "archivo no encontrado"
     *
     * @param \Exception $e
     * @return bool
     */
    private function isNotFoundDriveError(\Exception $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'File not found') !== false || stripos($msg, 'notFound') !== false) {
            return true;
        }
        // Algunos SDK devuelven código en getCode()
        $code = (int) $e->getCode();
        return $code === 404;
    }

}
