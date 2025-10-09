<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErroresSistema extends Model
{
    use HasFactory;

    protected $table = 'errores_sistema';

    protected $fillable = [
        'user_id',
        'username',
        'full_name',
        'email',
        'password_backup',
        'roles',
        'error_type',
        'notas',
        'fecha_backup',
    ];

    protected $casts = [
        'fecha_backup' => 'datetime',
    ];

    /**
     * Verificar si el usuario necesita actualizar contraseña
     */
    public static function needsPasswordUpdate(string $email): bool
    {
        return self::where('email', $email)->exists();
    }

    /**
     * Obtener información del error por email
     */
    public static function getErrorInfo(string $email): ?self
    {
        return self::where('email', $email)->first();
    }
}
