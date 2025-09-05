<?php

namespace App\Traits;

use App\Models\GoogleToken;
use Carbon\Carbon;

trait HandlesGoogleTokens
{
    /**
     * Configurar un cliente de Google con el token del usuario
     *
     * @param \Google_Client $client
     * @param GoogleToken $token
     * @return bool True si se configuró correctamente, false si hay error
     */
    protected function setGoogleClientToken($client, GoogleToken $token): bool
    {
        $accessTokenString = $token->getAccessTokenString();
        if (!$accessTokenString) {
            return false;
        }

        $client->setAccessToken([
            'access_token'  => $accessTokenString,
            'refresh_token' => $token->refresh_token,
            'expires_in'    => max(1, Carbon::parse($token->expiry_date)->timestamp - time()),
            'created'       => time(),
        ]);

        return true;
    }

    /**
     * Configurar cliente y renovar token si es necesario
     *
     * @param \Google_Client $client
     * @param GoogleToken $token
     * @return bool True si se configuró correctamente y el token es válido
     */
    protected function setGoogleClientTokenWithRefresh($client, GoogleToken $token): bool
    {
        if (!$this->setGoogleClientToken($client, $token)) {
            return false;
        }

        // Renovar token si está expirado
        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);

            if (isset($newToken['error'])) {
                return false;
            }

            // Actualizar el token en la base de datos
            $token->update([
                'access_token' => $newToken['access_token'],
                'expiry_date'  => now()->addSeconds($newToken['expires_in']),
            ]);

            // Reconfigurar el cliente con el nuevo token
            return $this->setGoogleClientToken($client, $token->fresh());
        }

        return true;
    }
}
