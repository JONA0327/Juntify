<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    /**
     * Especifica la conexión de base de datos
     */
    protected $connection = 'juntify_panels';

    /**
     * Nombre de la tabla
     */
    protected $table = 'empresa';

    /**
     * Los atributos que se pueden asignar masivamente
     */
    protected $fillable = [
        'iduser',
        'nombre_empresa',
        'rol',
        'es_administrador',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos
     */
    protected $casts = [
        'es_administrador' => 'boolean',
        'iduser' => 'string', // UUID desde la BD principal
    ];

    /**
     * Relación con los integrantes de la empresa
     */
    public function integrantes()
    {
        return $this->hasMany(IntegrantesEmpresa::class, 'empresa_id');
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
