<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // El esquema legacy utiliza transcriptions_laravel como fuente de reuniones.
        // Dejamos esta migración vacía para evitar crear una tabla "meetings" moderna
        // que colisione con la estructura existente.
    }

    public function down()
    {
        // Sin acciones: no se crea ni elimina la tabla legacy.
    }
};
