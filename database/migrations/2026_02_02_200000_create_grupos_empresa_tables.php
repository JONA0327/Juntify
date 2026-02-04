<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tablas para sistema de grupos en empresas (Panel DDU)
     */
    public function up(): void
    {
        // Tabla de grupos dentro de empresas
        Schema::connection('juntify_panels')->create('grupos_empresa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->uuid('created_by'); // Usuario que creó el grupo
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('cascade');
            $table->index(['empresa_id', 'is_active']);
        });

        // Tabla de miembros del grupo
        Schema::connection('juntify_panels')->create('miembros_grupo_empresa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->uuid('user_id');
            $table->enum('rol', ['administrador', 'colaborador', 'invitado'])->default('colaborador');
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('grupos_empresa')->onDelete('cascade');
            $table->unique(['grupo_id', 'user_id']);
            $table->index('user_id');
        });

        // Tabla de reuniones compartidas con el grupo
        Schema::connection('juntify_panels')->create('reuniones_compartidas_grupo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('meeting_id'); // ID en transcriptions_laravel
            $table->uuid('shared_by'); // Usuario que compartió (su token se usa para descargar)
            $table->json('permisos')->nullable(); // Permisos específicos: ver_audio, ver_transcript, descargar
            $table->text('mensaje')->nullable(); // Mensaje opcional al compartir
            $table->timestamp('expires_at')->nullable(); // Expiración opcional
            $table->timestamps();

            $table->foreign('grupo_id')->references('id')->on('grupos_empresa')->onDelete('cascade');
            $table->unique(['grupo_id', 'meeting_id']);
            $table->index('shared_by');
            $table->index('meeting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('juntify_panels')->dropIfExists('reuniones_compartidas_grupo');
        Schema::connection('juntify_panels')->dropIfExists('miembros_grupo_empresa');
        Schema::connection('juntify_panels')->dropIfExists('grupos_empresa');
    }
};
