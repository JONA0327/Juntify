<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    protected $fillable = [
        'google_token_id',
        'google_id',
        'name',
        'parent_id',
    ];

    public function getGoogleIdAttribute($value)
    {
        // Prefer plaintext column if present (legacy/backfill period)
        if (!empty($value)) {
            return $value;
        }
        // Otherwise, try decrypting stored encrypted value
        $enc = $this->attributes['google_id_enc'] ?? null;
        if (empty($enc)) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return $enc; // fallback: return as-is if not decryptable
        }
    }

    public function setGoogleIdAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['google_id_enc'] = null;
            $this->attributes['google_id_hash'] = null;
            $this->attributes['google_id'] = null; // avoid storing plaintext
            return;
        }

        $plain = (string) $value;
        $this->attributes['google_id_enc'] = \Illuminate\Support\Facades\Crypt::encryptString($plain);
        $this->attributes['google_id_hash'] = hash('sha256', $plain);
        // Do not persist plaintext
        $this->attributes['google_id'] = null;
    }
}
