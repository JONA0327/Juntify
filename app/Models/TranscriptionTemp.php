<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TranscriptionTemp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'audio_path',
        'transcription_path',
        'audio_size',
        'duration',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Obtener tiempo restante en formato legible
    public function getTimeRemainingAttribute()
    {
        $now = Carbon::now();
        $expires = $this->expires_at;

        if ($expires->isPast()) {
            return 'Expirado';
        }

        $diff = $now->diff($expires);

        if ($diff->days > 0) {
            return $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->i > 0) {
            return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '');
        } else {
            return 'Menos de 1 minuto';
        }
    }

    // Verificar si está expirado
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    // Scope para obtener solo los no expirados
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    // Scope para obtener solo los expirados
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }
}
