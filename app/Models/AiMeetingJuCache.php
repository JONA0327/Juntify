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
        'raw_encrypted_data',
        'checksum',
        'raw_checksum',
        'size_bytes',
        'raw_size_bytes',
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

    /**
     * Devuelve el JSON completo original del .ju (ya normalizado/parseado ANTES de reducir a summary/tasks/etc) si se guardó.
     */
    public function getRawDataAttribute(): ?array
    {
        if (empty($this->raw_encrypted_data)) { return null; }
        try {
            $json = Crypt::decryptString($this->raw_encrypted_data);
            $arr = json_decode($json, true);
            return is_array($arr) ? $arr : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Establece y encripta el JSON completo del .ju para poder reprocesarlo en el futuro.
     */
    public function setRawDataAttribute(?array $data): void
    {
        if ($data === null) { return; }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->attributes['raw_encrypted_data'] = Crypt::encryptString($json);
        $this->attributes['raw_checksum'] = hash('sha256', $json);
        $this->attributes['raw_size_bytes'] = strlen($json);
    }
}

<?php
$meeting = App\Models\TranscriptionLaravel::find(74);
app(App\Http\Controllers\AiAssistantController::class)->preloadMeeting(request(), 74);
