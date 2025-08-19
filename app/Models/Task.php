<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'meeting_id',
        'text',
        'description',
        'assignee',
        'due_date',
        'completed',
        'priority',
        'progress'
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed' => 'boolean',
        'progress' => 'integer'
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assignee', 'username');
    }

    public function meeting()
    {
        return $this->belongsTo(Container::class, 'meeting_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('completed', false);
    }

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('completed', false);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        return $this->due_date && $this->due_date->isPast() && !$this->completed;
    }

    public function getStatusAttribute()
    {
        if ($this->completed) {
            return 'completed';
        }

        if ($this->progress > 0 && $this->progress < 100) {
            return 'in_progress';
        }

        return 'pending';
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'completed' => 'green',
            default => 'gray'
        };
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'alta' => 'red',
            'media' => 'yellow',
            'baja' => 'green',
            default => 'gray'
        };
    }

    public function getTitleAttribute()
    {
        return $this->text;
    }
}
