<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DduAssistantMessage extends Model
{
    /**
     * La conexión de base de datos que debe usar el modelo.
     */
    protected $connection = 'juntify_panels';

    protected $table = 'ddu_assistant_messages';

    protected $fillable = [
        'assistant_conversation_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Relación con la conversación
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DduAssistantConversation::class, 'assistant_conversation_id');
    }
}
