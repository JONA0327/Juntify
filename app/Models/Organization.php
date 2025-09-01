<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    protected $fillable = [
        'nombre_organizacion',
        'descripcion',
        'imagen',
        'num_miembros',
        'admin_id',
    ];

    protected $casts = [
        'num_miembros' => 'integer',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'id_organizacion');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user', 'organization_id', 'user_id')
                    ->withPivot('rol')
                    ->withTimestamps();
    }

    public function refreshMemberCount(): int
    {
        $count = $this->users()->count();
        $this->update(['num_miembros' => $count]);

        return $count;
    }
}

