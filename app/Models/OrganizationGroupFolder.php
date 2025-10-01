<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationGroupFolder extends Model
{
    protected $table = 'organization_group_folders';

    protected $fillable = [
        'organization_id',
        'group_id',
        'organization_folder_id',
        'google_id',
        'name',
        'path_cached',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function documentosFolder(): BelongsTo
    {
        return $this->belongsTo(OrganizationFolder::class, 'organization_folder_id');
    }

    public function containerFolders(): HasMany
    {
        return $this->hasMany(OrganizationContainerFolder::class, 'organization_group_folder_id');
    }
}
