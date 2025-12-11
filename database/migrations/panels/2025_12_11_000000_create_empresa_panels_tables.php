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
        // Crear tabla empresa con campos UUID desde el principio
        Schema::connection('juntify_panels')->create('empresa', function (Blueprint $table) {
            $table->id();
            $table->char('iduser', 36); // UUID desde la BD principal de Juntify
            $table->string('nombre_empresa', 255);
            $table->enum('rol', ['founder', 'enterprise']);
            $table->boolean('es_administrador')->default(false);
            $table->timestamps();

            // Índice para mejorar las consultas
            $table->index('iduser');
        });

        // Crear tabla integrantes_empresa con campos UUID desde el principio
        Schema::connection('juntify_panels')->create('integrantes_empresa', function (Blueprint $table) {
            $table->id();
            $table->char('iduser', 36); // UUID desde la BD principal de Juntify
            $table->bigInteger('empresa_id')->unsigned(); // Relación con la tabla empresa
            $table->string('rol', 100);
            $table->json('permisos')->nullable(); // Array de permisos en formato JSON
            $table->timestamps();

            // Claves foráneas e índices
            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('cascade');
            $table->index('iduser');
            $table->index('empresa_id');

            // Evitar duplicados de usuario por empresa
            $table->unique(['iduser', 'empresa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar tablas en orden correcto (primero integrantes, luego empresa)
        Schema::connection('juntify_panels')->dropIfExists('integrantes_empresa');
        Schema::connection('juntify_panels')->dropIfExists('empresa');
    }
};
