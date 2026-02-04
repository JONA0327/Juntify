<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ReunionCompartidaGrupo extends Model
{
    protected $connection = 'juntify_panels';
    protected $table = 'reuniones_compartidas_grupo';

    protected $fillable = [
        'grupo_id',
        'meeting_id',
        'shared_by',
        'permisos',
        'mensaje',
        'expires_at',
    ];

    protected $casts = [
        'permisos' => 'array',
        'expires_at' => 'datetime',
    ];

    // Permisos disponibles
    const PERMISO_VER_AUDIO = 'ver_audio';
    const PERMISO_VER_TRANSCRIPT = 'ver_transcript';
    const PERMISO_DESCARGAR = 'descargar';

    /**
     * Permisos por defecto al compartir
     */
    public static function permisosDefault(): array
    {
        return [
            self::PERMISO_VER_AUDIO => true,
            self::PERMISO_VER_TRANSCRIPT => true,
            self::PERMISO_DESCARGAR => false,
        ];
    }

    /**
     * Grupo con el que se compartió
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoEmpresa::class, 'grupo_id');
    }

    /**
     * Usuario que compartió (su token se usa para acceder)
     */
    public function compartidoPor(): BelongsTo
    {
        return $this->setConnection('mysql')->belongsTo(User::class, 'shared_by');
    }

    /**
     * Verificar si tiene un permiso específico
     */
    public function tienePermiso(string $permiso): bool
    {
        $permisos = $this->permisos ?? self::permisosDefault();
        return $permisos[$permiso] ?? false;
    }

    /**
     * Verificar si el compartido ha expirado
     */
    public function haExpirado(): bool
    {
        if (!$this->expires_at) {
            return false; // Sin expiración = nunca expira
        }
        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Scope para compartidos no expirados
     */
    public function scopeVigentes($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    /**
     * Scope por grupo
     */
    public function scopeDeGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }
}
