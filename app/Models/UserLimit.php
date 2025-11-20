<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLimit extends Model
{
    use HasFactory;

    protected $table = 'limits';

    protected $fillable = [
        'username',
        'plan_code',
        'role',
        'daily_message_limit',
        'daily_session_limit',
        'can_upload_document',
        'has_premium_features',
        'additional_limits',
    ];

    protected $casts = [
        'can_upload_document' => 'boolean',
        'has_premium_features' => 'boolean',
        'additional_limits' => 'array',
    ];

    /**
     * Buscar límites por username
     */
    public static function forUser(string $username): ?self
    {
        return static::where('username', $username)->first();
    }

    /**
     * Crear límites por defecto para un usuario
     */
    public static function createDefault(string $username, string $planCode = 'free', string $role = 'user'): self
    {
        return static::create([
            'username' => $username,
            'plan_code' => $planCode,
            'role' => $role,
            'daily_message_limit' => 10,
            'daily_session_limit' => 3,
            'can_upload_document' => false,
            'has_premium_features' => false,
        ]);
    }
}
