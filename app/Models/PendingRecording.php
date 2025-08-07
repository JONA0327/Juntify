<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingRecording extends Model
{
    protected $fillable = [
        'user_id',
        'meeting_name',
        'audio_drive_id',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
