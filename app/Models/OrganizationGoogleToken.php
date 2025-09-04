<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = is_array($value) ? json_encode($value) : $value;
    }
}
