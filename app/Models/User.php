<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'current_organization_id',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function organizations()
    {
        return $this->hasManyThrough(
            Organization::class,
            Group::class,
            'id_organizacion', // Foreign key on groups table
            'id',              // Foreign key on organizations table
            'id',              // Local key on users table
            'id'               // Local key on groups table
        )->join('group_user', function ($join) {
            $join->on('groups.id', '=', 'group_user.id_grupo')
                 ->where('group_user.user_id', '=', $this->id);
        })->distinct();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user', 'user_id', 'id_grupo');
    }
}
