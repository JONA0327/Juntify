<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MeetingContentContainer;

class TranscriptionLaravel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'transcriptions_laravel';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'meeting_name',
        'audio_drive_id',
        'audio_download_url',
        'transcript_drive_id',
        'transcript_download_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function containers(): BelongsToMany
    {
        return $this->belongsToMany(MeetingContentContainer::class, 'meeting_content_relations', 'meeting_id', 'container_id');
    }

    public function keyPoints(): HasMany
    {
        return $this->hasMany(KeyPoint::class, 'meeting_id');
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(Transcription::class, 'meeting_id');
    }

    // Drive IDs encryption (transparent)
    public function getAudioDriveIdAttribute($value)
    {
        if (!empty($value)) { return $value; }
        $enc = $this->attributes['audio_drive_id_enc'] ?? null;
        if (empty($enc)) { return null; }
        try { return \Illuminate\Support\Facades\Crypt::decryptString($enc); }
        catch (\Throwable $e) { return $enc; }
    }

    public function setAudioDriveIdAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['audio_drive_id_enc'] = null;
            $this->attributes['audio_drive_id'] = null;
            return;
        }
        $plain = (string) $value;
        $this->attributes['audio_drive_id_enc'] = \Illuminate\Support\Facades\Crypt::encryptString($plain);
        $this->attributes['audio_drive_id'] = null;
    }

    public function getTranscriptDriveIdAttribute($value)
    {
        if (!empty($value)) { return $value; }
        $enc = $this->attributes['transcript_drive_id_enc'] ?? null;
        if (empty($enc)) { return null; }
        try { return \Illuminate\Support\Facades\Crypt::decryptString($enc); }
        catch (\Throwable $e) { return $enc; }
    }

    public function setTranscriptDriveIdAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['transcript_drive_id_enc'] = null;
            $this->attributes['transcript_drive_id'] = null;
            return;
        }
        $plain = (string) $value;
        $this->attributes['transcript_drive_id_enc'] = \Illuminate\Support\Facades\Crypt::encryptString($plain);
        $this->attributes['transcript_drive_id'] = null;
    }
}
