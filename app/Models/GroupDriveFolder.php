<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupDriveFolder extends Model
{
    protected $fillable = [
        'group_id',
        'organization_subfolder_id',
        'google_id',
        'name',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organizationSubfolder(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubfolder::class, 'organization_subfolder_id');
    }
}

