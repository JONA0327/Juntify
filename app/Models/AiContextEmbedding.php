<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiContextEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'content_type',
        'content_id',
        'content_snippet',
        'embedding_vector',
        'metadata',
    ];

    protected $casts = [
        'embedding_vector' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function scopeByUser($query, $username)
    {
        return $query->where('username', $username);
    }

    public function scopeByContentType($query, $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeByContent($query, $type, $id)
    {
        return $query->where('content_type', $type)->where('content_id', $id);
    }
}
