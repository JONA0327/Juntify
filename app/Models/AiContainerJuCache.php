<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiContainerJuCache extends Model
{
    protected $table = 'ai_container_ju_caches';

    protected $fillable = [
        'container_id',
        'encrypted_payload',
        'checksum',
        'size_bytes',
        'cached_at',
    ];

    protected $casts = [
        'cached_at' => 'datetime',
    ];

    public function getPayloadAttribute(): ?array
    {
        if (empty($this->encrypted_payload)) {
            return null;
        }

        try {
            $json = Crypt::decryptString($this->encrypted_payload);
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setPayloadAttribute(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->attributes['encrypted_payload'] = Crypt::encryptString($json);
        $this->attributes['checksum'] = hash('sha256', $json);
        $this->attributes['size_bytes'] = strlen($json);
        $this->attributes['cached_at'] = now();
    }
}
