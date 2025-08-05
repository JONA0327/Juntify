<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionLaravel extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'meeting_name',
        'audio_file_id',
        'audio_file_url',
        'transcript_file_id',
        'transcript_file_url',
        'transcript',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
