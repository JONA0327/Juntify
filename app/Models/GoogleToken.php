<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class GoogleToken extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'username',
        'access_token',
        'refresh_token',
        'expiry_date',
        'recordings_folder_id',
        // Nuevos campos separados
        'expires_in',
        'scope',
        'token_type',
        'id_token',
        'token_created_at',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
        'token_created_at' => 'datetime',
    ];

    public function setAccessTokenAttribute($value)
    {
        // Si es un array (token completo de Google), extraer componentes a campos separados
        if (is_array($value)) {
            $plainToken = $value['access_token'] ?? null;
            if ($plainToken === null) {
                $plainToken = json_encode($value);
            }
            $this->attributes['access_token'] = $this->encryptValue($plainToken);

            // Guardar componentes en campos separados si están disponibles
            if (isset($value['expires_in'])) {
                $this->attributes['expires_in'] = $value['expires_in'];
            }
            if (isset($value['scope'])) {
                $this->attributes['scope'] = $value['scope'];
            }
            if (isset($value['token_type'])) {
                $this->attributes['token_type'] = $value['token_type'];
            }
            if (isset($value['id_token'])) {
                $this->attributes['id_token'] = $value['id_token'];
            }
            if (isset($value['created'])) {
                $this->attributes['token_created_at'] = date('Y-m-d H:i:s', $value['created']);
            }
        } else {
            // String simple
            $this->attributes['access_token'] = $this->encryptValue($value);
        }
    }

    public function getAccessTokenAttribute($value)
    {
        if ($value === null) {
            return $value;
        }

        $decrypted = $this->decryptValue($value);

        // Si ya tenemos el access_token en un campo separado, retornarlo directamente como string
        if (!empty($decrypted) && !is_null($this->attributes['expires_in'] ?? null)) {
            return $decrypted;
        }

        // Lógica legacy para tokens guardados como JSON
        $decoded = json_decode($decrypted, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $decrypted;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $this->encryptValue($value);
    }

    public function getRefreshTokenAttribute($value)
    {
        if ($value === null) {
            return $value;
        }

        return $this->decryptValue($value);
    }

    /**
     * Obtener el access token como string, independientemente de cómo esté almacenado
     */
    public function getAccessTokenString()
    {
        $accessToken = $this->access_token;
        if (is_array($accessToken)) {
            return $accessToken['access_token'] ?? (is_string($accessToken) ? $accessToken : null);
        }
        return $accessToken;
    }

    /**
     * Verificar si tiene un access token válido
     */
    public function hasValidAccessToken()
    {
        $tokenString = $this->getAccessTokenString();
        return !empty($tokenString) && is_string($tokenString);
    }

    /**
     * Obtener el token completo como array para Google Client
     */
    public function getTokenArray(): array
    {
        $token = [
            'access_token' => $this->getAccessTokenString(),
            'refresh_token' => $this->refresh_token,
        ];

        // Agregar campos adicionales si están disponibles
        if ($this->expires_in) {
            $token['expires_in'] = $this->expires_in;
        }
        if ($this->scope) {
            $token['scope'] = $this->scope;
        }
        if ($this->token_type) {
            $token['token_type'] = $this->token_type;
        }
        if ($this->id_token) {
            $token['id_token'] = $this->id_token;
        }
        if ($this->token_created_at) {
            $token['created'] = $this->token_created_at->timestamp;
        } elseif ($this->expiry_date && $this->expires_in) {
            // Calcular created basado en expiry_date - expires_in
            $token['created'] = $this->expiry_date->timestamp - $this->expires_in;
        } else {
            $token['created'] = time();
        }

        return $token;
    }

    /**
     * Actualizar desde un token completo de Google
     */
    public function updateFromGoogleToken(array $googleToken): void
    {
        $updateData = [];

        if (isset($googleToken['access_token'])) {
            $updateData['access_token'] = $googleToken['access_token'];
        }

        if (isset($googleToken['expires_in'])) {
            $updateData['expires_in'] = $googleToken['expires_in'];
            $updateData['expiry_date'] = now()->addSeconds($googleToken['expires_in']);
        }

        if (isset($googleToken['scope'])) {
            $updateData['scope'] = $googleToken['scope'];
        }

        if (isset($googleToken['token_type'])) {
            $updateData['token_type'] = $googleToken['token_type'];
        }

        if (isset($googleToken['id_token'])) {
            $updateData['id_token'] = $googleToken['id_token'];
        }

        if (isset($googleToken['created'])) {
            $updateData['token_created_at'] = date('Y-m-d H:i:s', $googleToken['created']);
        } else {
            $updateData['token_created_at'] = now();
        }

        $this->update($updateData);
    }

    protected function encryptValue($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stringValue = is_string($value) ? $value : (string) $value;

        if ($this->isEncrypted($stringValue)) {
            return $stringValue;
        }

        return Crypt::encryptString($stringValue);
    }

    protected function decryptValue($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            return $value;
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    protected function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException $exception) {
            return false;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
