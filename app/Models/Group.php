<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'id_organizacion',
        'nombre_grupo',
        'descripcion',
        'miembros',
    ];

    protected $casts = [
        'id_organizacion' => 'integer',
        'miembros' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'id_organizacion');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user', 'id_grupo', 'user_id')
            ->withPivot('rol')
            ->withTimestamps();
    }

    public function containers(): HasMany
    {
        return $this->hasMany(MeetingContentContainer::class, 'group_id');
    }
}

