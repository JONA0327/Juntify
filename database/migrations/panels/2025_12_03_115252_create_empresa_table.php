<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('juntify_panels')->create('empresa', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('iduser')->unsigned(); // Relación con la BD de Juntify
            $table->string('nombre_empresa', 255);
            $table->enum('rol', ['founder', 'enterprise']);
            $table->boolean('es_administrador')->default(false);
            $table->timestamps();

            // Índice para mejorar las consultas
            $table->index('iduser');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('juntify_panels')->dropIfExists('empresa');
    }
};
