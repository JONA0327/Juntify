<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSubfolder extends Model
{
    protected $fillable = [
        'organization_folder_id',
        'google_id',
        'name',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(OrganizationFolder::class, 'organization_folder_id');
    }
}
