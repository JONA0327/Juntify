<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'from_user_id',
        'type',
        'title',
        'message',
        'data',
        'read',
        'read_at',
        // Mantener compatibilidad con campos antiguos
        'remitente',
        'emisor',
        'status'
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime'
    ];

    public $timestamps = true;

    // Nuevas relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromUser(): BelongsTo
    {
        // La tabla users usa UUIDs, pero notifications.remitente puede contener UUIDs
        return $this->belongsTo(User::class, 'remitente');
    }

    // Relaciones de compatibilidad (antiguos campos)
    public function remitente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitente');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emisor');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
