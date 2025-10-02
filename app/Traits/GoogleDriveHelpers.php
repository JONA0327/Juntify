<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait GoogleDriveHelpers
{
    /**
     * Cache of file IDs that are known to be invalid to avoid repeated API lookups.
     *
     * @var array<string, bool>
     */
    protected static array $invalidFileCache = [];

    /**
     * Cache of parent folder IDs that failed to resolve to reduce log noise.
     *
     * @var array<string, bool>
     */
    protected static array $invalidParentCache = [];

    protected function isInvalidFileId(string $fileId): bool
    {
        return isset(self::$invalidFileCache[$fileId]);
    }

    protected function markInvalidFileId(string $fileId): void
    {
        self::$invalidFileCache[$fileId] = true;
    }

    protected function isInvalidParentId(string $parentId): bool
    {
        return isset(self::$invalidParentCache[$parentId]);
    }

    protected function markInvalidParentId(string $parentId): void
    {
        self::$invalidParentCache[$parentId] = true;
    }

    protected function setGoogleDriveToken($userOrRequest)
    {
        $user = $userOrRequest instanceof Request ? $userOrRequest->user() : $userOrRequest;
        $googleToken = $user->googleToken;
        if (!$googleToken) {
            throw new \Exception('No se encontró token de Google para el usuario');
        }

        if (!$googleToken->access_token) {
            throw new \Exception('Token de acceso de Google no válido');
        }

        Log::info('setGoogleDriveToken: Setting token', [
            'username' => $user->username,
            'has_access_token' => !empty($googleToken->access_token),
            'has_refresh_token' => !empty($googleToken->refresh_token),
            'expiry_date' => $googleToken->expiry_date
        ]);

        $tokenData = $googleToken->access_token;
        if (is_string($tokenData)) {
            $decoded = json_decode($tokenData, true);
            $tokenData = json_last_error() === JSON_ERROR_NONE ? $decoded : $tokenData;
        }

        $this->googleDriveService->setAccessToken($tokenData);

        if ($this->googleDriveService->getClient()->isAccessTokenExpired()) {
            Log::info('setGoogleDriveToken: Google Client says token is expired, refreshing');
            try {
                if (!$googleToken->refresh_token) {
                    throw new \Exception('No hay refresh token disponible');
                }
                $newTokens = $this->googleDriveService->refreshToken($googleToken->refresh_token);
                $googleToken->update([
                    'access_token' => $newTokens,
                    'expiry_date' => now()->addSeconds($newTokens['expires_in'] ?? 3600)
                ]);
                Log::info('setGoogleDriveToken: Token refreshed successfully', [
                    'new_expiry' => now()->addSeconds($newTokens['expires_in'] ?? 3600)
                ]);
            } catch (\Exception $e) {
                Log::error('setGoogleDriveToken: Error refreshing token', [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
    }

    protected function getFolderName($fileId): string
    {
        if (empty($fileId)) {
            return 'Sin especificar';
        }

        if ($this->isInvalidFileId($fileId)) {
            return 'Archivo no encontrado';
        }

        $attempts = [];

        // 1) Primer intento: token OAuth del usuario (googleDriveService)
        $attempts[] = 'oauth';
        try {
            return $this->resolveFolderNameViaDrive($fileId, 'oauth');
        } catch (\Exception $eFirst) {
            $firstMsg = $eFirst->getMessage();
            // Log sólo primera vez
            if (!$this->isInvalidFileId($fileId)) {
                Log::debug('getFolderName: Primer intento fallido (oauth)', [
                    'file_id' => $fileId,
                    'error' => $firstMsg
                ]);
            }

            $isPermissionOrNotFound = str_contains($firstMsg, 'File not found') ||
                str_contains($firstMsg, '404') ||
                str_contains($firstMsg, 'notFound') ||
                str_contains($firstMsg, 'PERMISSION') ||
                str_contains($firstMsg, 'forbidden') ||
                str_contains($firstMsg, 'unauthorized');

            // 2) Fallback: Service Account (sin o con impersonation si luego se amplía)
            if ($isPermissionOrNotFound) {
                if (app()->bound(\App\Services\GoogleServiceAccount::class)) {
                    $attempts[] = 'service_account';
                    try {
                        return $this->resolveFolderNameViaDrive($fileId, 'service_account');
                    } catch (\Exception $eSa) {
                        Log::debug('getFolderName: Fallback service account falló', [
                            'file_id' => $fileId,
                            'error' => $eSa->getMessage()
                        ]);
                        // Continuar hacia marcado inválido abajo
                        $firstMsg = $eSa->getMessage(); // usar último mensaje para clasificación
                    }
                } else {
                    Log::debug('getFolderName: ServiceAccount no está enlazado en el contenedor IoC');
                }
            }

            // Clasificación final
            if (str_contains($firstMsg, 'File not found') ||
                str_contains($firstMsg, '404') ||
                str_contains($firstMsg, 'notFound')) {
                $this->markInvalidFileId($fileId);
                return 'Archivo no encontrado';
            }

            if (str_contains($firstMsg, 'API key') ||
                str_contains($firstMsg, 'PERMISSION_DENIED') ||
                str_contains($firstMsg, 'unauthorized') ||
                str_contains($firstMsg, 'forbidden')) {
                // Devolvemos nombre genérico para no romper UI
                return 'Juntify Recordings';
            }

            $this->markInvalidFileId($fileId);
            return 'Error al obtener carpeta';
        }
    }

    /**
     * Resuelve el nombre de la carpeta usando el tipo de driver indicado.
     * @throws \Exception
     */
    protected function resolveFolderNameViaDrive(string $fileId, string $driverType): string
    {
        if ($driverType === 'service_account') {
            /** @var \App\Services\GoogleServiceAccount $drv */
            $drv = app(\App\Services\GoogleServiceAccount::class);
            $file = $drv->getFileInfo($fileId);
        } else { // oauth
            $file = $this->googleDriveService->getFileInfo($fileId);
        }

        if ($file->getParents()) {
            $parentId = $file->getParents()[0];
            try {
                if ($this->isInvalidParentId($parentId)) {
                    return 'Carpeta no disponible';
                }
                if ($driverType === 'service_account') {
                    /** @var \App\Services\GoogleServiceAccount $drv */
                    $drv = app(\App\Services\GoogleServiceAccount::class);
                    $parent = $drv->getFileInfo($parentId);
                } else {
                    $parent = $this->googleDriveService->getFileInfo($parentId);
                }
                return $parent->getName() ?: 'Carpeta sin nombre';
            } catch (\Exception $parentException) {
                Log::debug('getFolderName: Error getting parent folder name', [
                    'parent_id' => $parentId,
                    'driver' => $driverType,
                    'error' => $parentException->getMessage()
                ]);
                $msg = $parentException->getMessage();
                if (str_contains($msg, 'File not found') ||
                    str_contains($msg, '404') ||
                    str_contains($msg, 'notFound') ||
                    str_contains($msg, 'forbidden') ||
                    str_contains($msg, 'PERMISSION_DENIED') ||
                    str_contains($msg, 'unauthorized')) {
                    $this->markInvalidParentId($parentId);
                }
                return 'Carpeta no disponible';
            }
        }

        return 'Carpeta raíz';
    }

    /**
     * Pequeño wrapper para descargar contenido desde Drive en clases que usan este trait.
     */
    protected function downloadFromDrive(string $fileId)
    {
        return $this->googleDriveService->downloadFileContent($fileId);
    }
}

