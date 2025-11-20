<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatSession extends Model
{
    use HasFactory;
    protected $table = 'conversations';

    protected $fillable = [
        'username',
        'title',
        'context_data',
        'context_type',
        'context_id',
        'is_active',
        'last_activity',
    ];

    protected $casts = [
        'context_data' => 'array',
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByUser($query, $username)
    {
        return $query->where('username', $username);
    }

    public function updateActivity()
    {
        $this->update(['last_activity' => now()]);
    }
}
