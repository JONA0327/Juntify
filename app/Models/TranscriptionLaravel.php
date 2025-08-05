<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
