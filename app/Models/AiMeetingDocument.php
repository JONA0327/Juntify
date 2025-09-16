<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TranscriptionLaravel;

class AiMeetingDocument extends Model
{
    use HasFactory;

    public const MEETING_TYPE_LEGACY = 'legacy';

    protected $fillable = [
        'document_id',
        'meeting_id',
        'meeting_type',
        'assigned_by_username',
        'assignment_note',
    ];

    protected $attributes = [
        'meeting_type' => self::MEETING_TYPE_LEGACY,
    ];

    protected static function booted(): void
    {
        static::creating(function (AiMeetingDocument $assignment) {
            $assignment->meeting_type = self::MEETING_TYPE_LEGACY;
        });

        static::updating(function (AiMeetingDocument $assignment) {
            $assignment->meeting_type = self::MEETING_TYPE_LEGACY;
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'document_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_username', 'username');
    }

    // Relación simplificada: todas las reuniones apuntan al esquema legacy
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
    }

    public function setMeetingTypeAttribute($value): void
    {
        $this->attributes['meeting_type'] = self::MEETING_TYPE_LEGACY;
    }

    public function scopeByMeeting($query, $meetingId, $meetingType = self::MEETING_TYPE_LEGACY)
    {
        return $query->where('meeting_id', $meetingId)
            ->where('meeting_type', self::MEETING_TYPE_LEGACY);
    }
}
