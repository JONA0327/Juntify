<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasFactory;
    protected $table = 'conversation_messages';

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'metadata',
        'attachments',
        'is_hidden',
    ];

    protected $casts = [
        'metadata' => 'array',
        'attachments' => 'array',
        'is_hidden' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }
}
