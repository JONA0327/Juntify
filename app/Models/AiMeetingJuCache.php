<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiMeetingJuCache extends Model
{
    protected $table = 'ai_meeting_ju_caches';

    protected $fillable = [
        'meeting_id',
        'transcript_drive_id',
        'encrypted_data',
        'checksum',
        'size_bytes',
        'cached_at',
    ];

    protected $casts = [
        'cached_at' => 'datetime',
    ];

    public function getDataAttribute(): ?array
    {
        try {
            $json = Crypt::decryptString($this->encrypted_data);
            $arr = json_decode($json, true);
            return is_array($arr) ? $arr : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setDataAttribute(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->attributes['encrypted_data'] = Crypt::encryptString($json);
        $this->attributes['checksum'] = hash('sha256', $json);
        $this->attributes['size_bytes'] = strlen($json);
        $this->attributes['cached_at'] = now();
    }
}
