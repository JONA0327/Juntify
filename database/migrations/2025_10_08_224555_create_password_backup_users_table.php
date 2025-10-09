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
        Schema::create('password_backup_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique(); // Email del usuario que necesita actualizar contraseña
            $table->string('username')->nullable(); // Username si está disponible
            $table->string('full_name')->nullable(); // Nombre completo si está disponible
            $table->text('password_backup')->nullable(); // Contraseña de backup si existe
            $table->string('error_type')->default('usuario_no_existe_en_backup'); // Tipo de error
            $table->text('notas')->nullable(); // Notas adicionales
            $table->timestamp('fecha_backup')->nullable(); // Fecha del backup original
            $table->boolean('notified')->default(false); // Si ya se le notificó al usuario
            $table->timestamp('notification_sent_at')->nullable(); // Cuándo se envió la notificación
            $table->boolean('password_updated')->default(false); // Si ya actualizó la contraseña
            $table->timestamp('password_updated_at')->nullable(); // Cuándo actualizó la contraseña
            $table->timestamps();

            // Indexes para optimizar búsquedas
            $table->index(['email', 'notified']);
            $table->index(['email', 'password_updated']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_backup_users');
    }
};
