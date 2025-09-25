<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
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
        return !empty($this->access_token) && !empty($this->refresh_token);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getAccessTokenAttribute($value)
    {
        if (is_null($value)) {
            return $value;
        }

        try {
            $value = Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            // Valor legacy sin cifrar
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function setAccessTokenAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['access_token'] = null;
            return;
        }

        $rawValue = is_array($value) ? json_encode($value) : (string) $value;
        $this->attributes['access_token'] = Crypt::encryptString($rawValue);
    }

    public function getRefreshTokenAttribute($value)
    {
        if (is_null($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $exception) {
            return $value;
        }
    }

    public function setRefreshTokenAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['refresh_token'] = null;
            return;
        }

        $this->attributes['refresh_token'] = Crypt::encryptString((string) $value);
    }
}
