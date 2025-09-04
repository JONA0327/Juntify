<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationFolder extends Model
{
    protected $fillable = [
        'organization_id',
        'organization_google_token_id',
        'google_id',
        'name',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function googleToken(): BelongsTo
    {
        return $this->belongsTo(OrganizationGoogleToken::class, 'organization_google_token_id');
    }

    public function subfolders(): HasMany
    {
        return $this->hasMany(OrganizationSubfolder::class);
    }
}
