<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DduAssistantConversation extends Model
{
    /**
     * La conexión de base de datos que debe usar el modelo.
     */
    protected $connection = 'juntify_panels';

    protected $table = 'ddu_assistant_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'description',
    ];

    /**
     * Relación con los mensajes
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DduAssistantMessage::class, 'assistant_conversation_id');
    }

    /**
     * Relación con los documentos
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DduAssistantDocument::class, 'assistant_conversation_id');
    }

    /**
     * Obtener el conteo de mensajes
     */
    public function getMessagesCountAttribute(): int
    {
        return $this->messages()->count();
    }
}
