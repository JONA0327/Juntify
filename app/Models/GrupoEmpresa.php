<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoEmpresa extends Model
{
    protected $connection = 'juntify_panels';
    protected $table = 'grupos_empresa';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Empresa a la que pertenece el grupo
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Usuario que creó el grupo
     */
    public function creador(): BelongsTo
    {
        return $this->setConnection('mysql')->belongsTo(User::class, 'created_by');
    }

    /**
     * Miembros del grupo
     */
    public function miembros(): HasMany
    {
        return $this->hasMany(MiembroGrupoEmpresa::class, 'grupo_id');
    }

    /**
     * Reuniones compartidas con el grupo
     */
    public function reunionesCompartidas(): HasMany
    {
        return $this->hasMany(ReunionCompartidaGrupo::class, 'grupo_id');
    }

    /**
     * Scope para grupos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para grupos de una empresa específica
     */
    public function scopeDeEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
