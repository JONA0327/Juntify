<?php

namespace App\Services;

use App\Models\GoogleToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleTokenRefreshService
{
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Refrescar token para un usuario específico
     */
    public function refreshTokenForUser(User $user): bool
    {
        $token = GoogleToken::where('username', $user->username)->first();

        if (!$token || !$token->refresh_token) {
            Log::warning("Usuario {$user->username} no tiene token de Google o refresh_token");
            return false;
        }

        return $this->refreshToken($token);
    }

    /**
     * Refrescar un token específico
     */
    public function refreshToken(GoogleToken $token): bool
    {
        try {
            $client = $this->googleDriveService->getClient();

            // Usar el método del modelo para obtener el token como array completo
            $tokenArray = $token->getTokenArray();
            if (empty($tokenArray['access_token'])) {
                Log::error("Token inválido para usuario {$token->username}: access_token vacío");
                return false;
            }

            // Configurar el token actual
            $client->setAccessToken($tokenArray);

            // Verificar si está expirado y renovar
            if ($client->isAccessTokenExpired() && $token->refresh_token) {
                Log::info("Renovando token para usuario: {$token->username}");

                $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);

                if (isset($newToken['error'])) {
                    Log::error("Error al renovar token para {$token->username}: " . $newToken['error']);
                    return false;
                }

                // Actualizar el token usando el nuevo método del modelo
                $token->updateFromGoogleToken($newToken);

                Log::info("Token renovado exitosamente para usuario: {$token->username}");
                return true;
            }

            // El token aún es válido
            return true;

        } catch (\Exception $e) {
            Log::error("Error al renovar token para {$token->username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si un token necesita ser renovado (expira en los próximos 5 minutos)
     */
    public function tokenNeedsRefresh(GoogleToken $token): bool
    {
        if (!$token->expiry_date) {
            return true;
        }

        // Renovar si expira en los próximos 5 minutos
        $expiresIn = Carbon::parse($token->expiry_date)->timestamp - time();
        return $expiresIn <= 300; // 5 minutos
    }

    /**
     * Renovar todos los tokens que están próximos a expirar
     */
    public function refreshExpiredTokens(): int
    {
        $refreshed = 0;
        $tokens = GoogleToken::where('expiry_date', '<=', now()->addMinutes(5))
                            ->whereNotNull('refresh_token')
                            ->get();

        foreach ($tokens as $token) {
            if ($this->refreshToken($token)) {
                $refreshed++;
            }
        }

        Log::info("Renovados {$refreshed} tokens de Google");
        return $refreshed;
    }

    /**
     * Verificar el estado de conexión para un usuario
     */
    public function checkConnectionStatus(User $user): array
    {
        $token = GoogleToken::where('username', $user->username)->first();

        if (!$token || !$token->hasValidAccessToken()) {
            return [
                'drive_connected' => false,
                'needs_reconnection' => true,
                'message' => 'No hay token de Google configurado'
            ];
        }

        // Intentar renovar el token si es necesario
        if ($this->tokenNeedsRefresh($token)) {
            if (!$this->refreshToken($token)) {
                return [
                    'drive_connected' => false,
                    'needs_reconnection' => true,
                    'message' => 'Token expirado y no se pudo renovar'
                ];
            }
        }

        // Verificar conexión a Drive
        $driveConnected = $this->testDriveConnection($token);

        return [
            'drive_connected' => $driveConnected,
            'needs_reconnection' => !$driveConnected,
            'message' => $driveConnected ? 'Conexión activa' : 'Problemas de conexión'
        ];
    }

    /**
     * Probar conexión a Google Drive
     */
    private function testDriveConnection(GoogleToken $token): bool
    {
        try {
            $client = $this->googleDriveService->getClient();

            // Usar el método del modelo para obtener el token como array completo
            $tokenArray = $token->getTokenArray();
            if (empty($tokenArray['access_token'])) {
                return false;
            }

            $client->setAccessToken($tokenArray);

            // Hacer una petición simple para verificar la conexión
            $drive = $this->googleDriveService->getDrive();
            $drive->about->get(['fields' => 'user']);

            return true;
        } catch (\Exception $e) {
            Log::warning("Falló test de conexión a Drive para {$token->username}: " . $e->getMessage());
            return false;
        }
    }
}
