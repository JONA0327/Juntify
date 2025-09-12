<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTaskDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'task_id',
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

    public function scopeByTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }
}
