<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait GoogleDriveHelpers
{
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
        try {
            if (empty($fileId)) {
                return 'Sin especificar';
            }

            $file = $this->googleDriveService->getFileInfo($fileId);

            if ($file->getParents()) {
                $parentId = $file->getParents()[0];
                try {
                    $parent = $this->googleDriveService->getFileInfo($parentId);
                    return $parent->getName() ?: 'Carpeta sin nombre';
                } catch (\Exception $parentException) {
                    Log::warning('getFolderName: Error getting parent folder name', [
                        'parent_id' => $parentId,
                        'error' => $parentException->getMessage()
                    ]);
                    return 'Carpeta no disponible';
                }
            }

            return 'Carpeta raíz';
        } catch (\Exception $e) {
            Log::warning('getFolderName: Error getting folder name', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);

            // Handle specific error cases
            if (str_contains($e->getMessage(), 'File not found') ||
                str_contains($e->getMessage(), '404') ||
                str_contains($e->getMessage(), 'notFound')) {
                return 'Archivo no encontrado';
            }

            if (str_contains($e->getMessage(), 'API key') ||
                str_contains($e->getMessage(), 'PERMISSION_DENIED') ||
                str_contains($e->getMessage(), 'unauthorized') ||
                str_contains($e->getMessage(), 'forbidden')) {
                return 'Juntify Recordings';
            }

            return 'Error al obtener carpeta';
        }
    }

    /**
     * Pequeño wrapper para descargar contenido desde Drive en clases que usan este trait.
     */
    protected function downloadFromDrive(string $fileId)
    {
        return $this->googleDriveService->downloadFileContent($fileId);
    }
}

