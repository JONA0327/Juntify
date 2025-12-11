<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrantesEmpresa extends Model
{
    use HasFactory;

    /**
     * Especifica la conexión de base de datos
     */
    protected $connection = 'juntify_panels';

    /**
     * Nombre de la tabla
     */
    protected $table = 'integrantes_empresa';

    /**
     * Los atributos que se pueden asignar masivamente
     */
    protected $fillable = [
        'iduser',
        'empresa_id',
        'rol',
        'permisos',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos
     */
    protected $casts = [
        'permisos' => 'array',
        'iduser' => 'string', // UUID desde la BD principal
    ];

    /**
     * Relación con la empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Obtener el usuario desde la BD principal por iduser
     * Nota: Esta es una relación cross-database que requiere manejo especial
     */
    public function getUsuarioAttribute()
    {
        return User::find($this->iduser);
    }
}
