<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SharedMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
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
    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function sharedWith()
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
