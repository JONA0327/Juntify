<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\GoogleToken;

class User extends Authenticatable
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'username',
        'full_name',
        'email',
        'password',
        'roles',           // ahora un string
        'organization',
        'plan_expires_at',
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($u) {
            if (empty($u->{$u->getKeyName()})) {
                $u->{$u->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function googleToken(): HasOne
    {
        return $this->hasOne(GoogleToken::class, 'username', 'username');
    }
}
