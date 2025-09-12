<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMeetingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'meeting_id',
        'meeting_type',
        'assigned_by_username',
        'assignment_note',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'document_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_username', 'username');
    }

    // Relación polimórfica para reuniones legacy y modernas
    public function meeting()
    {
        if ($this->meeting_type === 'legacy') {
            return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
        } else {
            return $this->belongsTo(Meeting::class, 'meeting_id');
        }
    }

    public function scopeByMeeting($query, $meetingId, $meetingType = 'legacy')
    {
        return $query->where('meeting_id', $meetingId)->where('meeting_type', $meetingType);
    }
}
