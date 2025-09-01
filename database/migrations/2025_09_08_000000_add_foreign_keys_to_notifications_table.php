<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Intentar soltar FKs previas si existen
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['remitente']);
            });
        } catch (\Throwable $e) {}
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['emisor']);
            });
        } catch (\Throwable $e) {}

        // Alinear tipos: users.id es UUID (CHAR(36)). Cambiar columnas a CHAR(36) NULL
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE notifications MODIFY COLUMN remitente CHAR(36) NULL');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE notifications MODIFY COLUMN emisor CHAR(36) NULL');

        // Limpiar valores huérfanos que no existen en users
        \Illuminate\Support\Facades\DB::statement('UPDATE notifications n LEFT JOIN users u ON n.remitente = u.id SET n.remitente = NULL WHERE u.id IS NULL');
        \Illuminate\Support\Facades\DB::statement('UPDATE notifications n LEFT JOIN users u ON n.emisor = u.id SET n.emisor = NULL WHERE u.id IS NULL');

        // Crear FKs compatibles; usar SET NULL para seguridad
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('remitente')->references('id')->on('users')->onDelete('set null');
            $table->foreign('emisor')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['remitente']);
            $table->dropForeign(['emisor']);
        });
        // Revertir tipos si fuese necesario
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE notifications MODIFY COLUMN remitente BIGINT UNSIGNED NULL');
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE notifications MODIFY COLUMN emisor BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {}
    }
};
