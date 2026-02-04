<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiembroGrupoEmpresa extends Model
{
    protected $connection = 'juntify_panels';
    protected $table = 'miembros_grupo_empresa';

    protected $fillable = [
        'grupo_id',
        'user_id',
        'rol',
    ];

    const ROL_ADMINISTRADOR = 'administrador';
    const ROL_COLABORADOR = 'colaborador';
    const ROL_INVITADO = 'invitado';

    /**
     * Grupo al que pertenece
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoEmpresa::class, 'grupo_id');
    }

    /**
     * Usuario miembro (de la base de datos principal)
     */
    public function user(): BelongsTo
    {
        return $this->setConnection('mysql')->belongsTo(User::class, 'user_id');
    }

    /**
     * Verificar si es administrador
     */
    public function esAdministrador(): bool
    {
        return $this->rol === self::ROL_ADMINISTRADOR;
    }

    /**
     * Verificar si es colaborador
     */
    public function esColaborador(): bool
    {
        return $this->rol === self::ROL_COLABORADOR;
    }

    /**
     * Verificar si es invitado
     */
    public function esInvitado(): bool
    {
        return $this->rol === self::ROL_INVITADO;
    }

    /**
     * Scope por rol
     */
    public function scopeConRol($query, $rol)
    {
        return $query->where('rol', $rol);
    }
}
