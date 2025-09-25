<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class OrganizationGoogleToken extends Model
{
    protected $fillable = [
        'organization_id',
        'access_token',
        'refresh_token',
        'expiry_date',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function isConnected(): bool
    {
        return !empty($this->getAccessTokenString()) && !empty($this->refresh_token);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function setAccessTokenAttribute($value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $this->attributes['access_token'] = $this->encryptValue($value);
    }

    public function getAccessTokenAttribute($value)
    {
        if ($value === null) {
            return $value;
        }

        $decrypted = $this->decryptValue($value);
        $decoded   = json_decode($decrypted, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded['access_token'] ?? $decoded;
        }

        return $decrypted;
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

    public function getAccessTokenString(): ?string
    {
        $accessToken = $this->access_token;

        if (is_array($accessToken)) {
            return $accessToken['access_token'] ?? null;
        }

        return $accessToken ?: null;
    }

    public function getTokenArray(): array
    {
        $token = [];

        if ($accessToken = $this->getAccessTokenString()) {
            $token['access_token'] = $accessToken;
        }

        if ($refreshToken = $this->refresh_token) {
            $token['refresh_token'] = $refreshToken;
        }

        if ($this->expiry_date) {
            $token['expiry_date'] = $this->expiry_date->timestamp;
        }

        return $token;
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
