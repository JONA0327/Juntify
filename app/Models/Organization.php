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
    ];

    protected $casts = [
        'num_miembros' => 'integer',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'id_organizacion');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user', 'id_organizacion', 'user_id');
    }
}

