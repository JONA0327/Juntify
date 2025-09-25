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

    public function getGoogleIdAttribute($value)
    {
        if (!empty($value)) { return $value; }
        $enc = $this->attributes['google_id_enc'] ?? null;
        if (empty($enc)) { return null; }
        try { return \Illuminate\Support\Facades\Crypt::decryptString($enc); }
        catch (\Throwable $e) { return $enc; }
    }

    public function setGoogleIdAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['google_id_enc'] = null;
            $this->attributes['google_id_hash'] = null;
            $this->attributes['google_id'] = null;
            return;
        }
        $plain = (string) $value;
        $this->attributes['google_id_enc'] = \Illuminate\Support\Facades\Crypt::encryptString($plain);
        $this->attributes['google_id_hash'] = hash('sha256', $plain);
        $this->attributes['google_id'] = null;
    }
}

