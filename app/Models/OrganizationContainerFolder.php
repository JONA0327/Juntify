<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationContainerFolder extends Model
{
    protected $table = 'organization_container_folders';

    protected $fillable = [
        'organization_id',
        'group_id',
        'container_id',
        'organization_group_folder_id',
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

    public function container(): BelongsTo
    {
        return $this->belongsTo(MeetingContentContainer::class, 'container_id');
    }

    public function groupFolder(): BelongsTo
    {
        return $this->belongsTo(OrganizationGroupFolder::class, 'organization_group_folder_id');
    }
}
