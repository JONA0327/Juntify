<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DduAssistantDocument extends Model
{
    /**
     * La conexión de base de datos que debe usar el modelo.
     */
    protected $connection = 'juntify_panels';

    protected $table = 'ddu_assistant_documents';

    protected $fillable = [
        'assistant_conversation_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'extracted_text',
        'summary',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    /**
     * Relación con la conversación
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DduAssistantConversation::class, 'assistant_conversation_id');
    }
}
