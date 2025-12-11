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
        Schema::connection('juntify_panels')->create('integrantes_empresa', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('iduser')->unsigned(); // Relación con la BD de Juntify
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
        Schema::connection('juntify_panels')->dropIfExists('integrantes_empresa');
    }
};
