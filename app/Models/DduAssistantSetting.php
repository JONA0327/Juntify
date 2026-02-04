<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DduAssistantSetting extends Model
{
    /**
     * La conexión de base de datos que debe usar el modelo.
     */
    protected $connection = 'juntify_panels';

    protected $table = 'ddu_assistant_settings';

    protected $fillable = [
        'user_id',
        'openai_api_key',
        'enable_drive_calendar',
    ];

    protected $casts = [
        'enable_drive_calendar' => 'boolean',
    ];

    protected $hidden = [
        'openai_api_key', // No exponer en serialización normal
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Encriptar API key al guardar
     */
    public function setOpenaiApiKeyAttribute(?string $value): void
    {
        $this->attributes['openai_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Desencriptar API key al leer
     */
    public function getDecryptedApiKey(): ?string
    {
        if (!isset($this->attributes['openai_api_key']) || !$this->attributes['openai_api_key']) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['openai_api_key']);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Verificar si tiene API key configurada
     */
    public function hasApiKey(): bool
    {
        return !empty($this->attributes['openai_api_key']);
    }
}
