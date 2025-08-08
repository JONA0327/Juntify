<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingRecording extends Model
{
    public const STATUS_PENDING    = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_COMPLETED  = 'COMPLETED';
    public const STATUS_FAILED     = 'FAILED';

    protected $fillable = [
        'username',
        'meeting_name',
        'audio_drive_id',
        'audio_download_url',
        'status',
        'error_message',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
