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
        Schema::create('company_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('company_panel_id', 36); // ID del panel de la empresa
            $table->char('user_id', 36); // ID del usuario de la tabla users
            $table->string('custom_role', 100); // Rol personalizado asignado por el administrador
            $table->boolean('is_active')->default(true); // Si el usuario está activo en la empresa
            $table->timestamp('joined_at')->useCurrent(); // Cuando se agregó el usuario a la empresa
            $table->char('added_by', 36)->nullable(); // Quién agregó al usuario (administrador)
            $table->text('notes')->nullable(); // Notas adicionales sobre el usuario
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_panel_id')->references('id')->on('user_panel_administrativo')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();

            // Unique constraint: un usuario solo puede estar una vez en cada empresa
            $table->unique(['company_panel_id', 'user_id']);

            // Indexes para mejorar performance
            $table->index(['company_panel_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_users');
    }
};
