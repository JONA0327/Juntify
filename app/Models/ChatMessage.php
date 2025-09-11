<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class ChatMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'chat_id',
        'sender_id',
        'body',
        'file_path',
        'voice_path',
        'created_at',
        'read_at',
    ];

    protected $dates = ['created_at', 'read_at'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function setBodyAttribute($value): void
    {
        $this->attributes['body'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getBodyAttribute($value)
    {
        if (!$value) return null;
        // Intentar desencriptar valor actual
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Fallback: mensajes legacy almacenados como base64 (JSON o texto)
            try {
                if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $value) && strlen($value) % 4 === 0) {
                    $decoded = base64_decode($value, true);
                    if ($decoded !== false && $decoded !== '') {
                        // Si es JSON y contiene "body" devolver ese campo
                        $json = json_decode($decoded, true);
                        if (is_array($json) && isset($json['body']) && is_string($json['body'])) {
                            return $json['body'];
                        }
                        // Si decodificado parece texto legible devolverlo
                        if (preg_match('/[\x20-\x7E]/', $decoded)) {
                            return $decoded;
                        }
                    }
                }
            } catch (\Throwable $inner) {
                // Ignorar y devolver valor crudo
            }
            // Último recurso: devolver el valor original (evita 500)
            return $value;
        }
    }
}
