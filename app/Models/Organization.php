<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function folder(): HasOne
    {
        return $this->hasOne(OrganizationFolder::class);
    }

    public function googleToken(): HasOne
    {
        return $this->hasOne(OrganizationGoogleToken::class);
    }

    public function subfolders(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrganizationSubfolder::class,
            OrganizationFolder::class,
            'organization_id',      // Foreign key on organization_folders table
            'organization_folder_id', // Foreign key on organization_subfolders table
            'id',                    // Local key on organizations table
            'id'                     // Local key on organization_folders table
        );
    }
}

