<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TranscriptionLaravel;
use App\Models\TranscriptionTemp;
use App\Models\User;

class SharedMeeting extends Model
{
    use HasFactory;

    protected $table = 'shared_meetings';

    protected $fillable = [
        'meeting_id',
        'meeting_type',
        'shared_by',
        'shared_with',
        'status',
        'shared_at',
        'responded_at',
        'message'
    ];

    protected $casts = [
        'shared_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    // Relaciones
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
    }

    public function temporaryMeeting(): BelongsTo
    {
        return $this->belongsTo(TranscriptionTemp::class, 'meeting_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    /** User who received the share */
    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with');
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('shared_with', $userId);
    }

    public function scopeSharedByUser($query, $userId)
    {
        return $query->where('shared_by', $userId);
    }
}
