<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordBackupUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'username',
        'full_name',
        'password_backup',
        'error_type',
        'notas',
        'fecha_backup',
        'notified',
        'notification_sent_at',
        'password_updated',
        'password_updated_at',
    ];

    protected $casts = [
        'fecha_backup' => 'datetime',
        'notification_sent_at' => 'datetime',
        'password_updated_at' => 'datetime',
        'notified' => 'boolean',
        'password_updated' => 'boolean',
    ];

    /**
     * Verificar si el usuario necesita actualizar contraseña
     */
    public static function needsPasswordUpdate(string $email): bool
    {
        return self::where('email', $email)
            ->where('password_updated', false)
            ->exists();
    }

    /**
     * Marcar como notificado
     */
    public function markAsNotified(): void
    {
        $this->update([
            'notified' => true,
            'notification_sent_at' => now(),
        ]);
    }

    /**
     * Marcar como contraseña actualizada
     */
    public function markPasswordUpdated(): void
    {
        $this->update([
            'password_updated' => true,
            'password_updated_at' => now(),
        ]);
    }
}
