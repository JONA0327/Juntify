<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        // Detectar y soltar la FK de from_user_id si existe
        $dbName = DB::getDatabaseName();
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'from_user_id' AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$dbName]
        );
        foreach ($constraints as $row) {
            DB::statement("ALTER TABLE `notifications` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
        }

        if (Schema::hasColumn('notifications', 'from_user_id')) {
            // Convertir a CHAR(36) para UUID
            DB::statement("ALTER TABLE `notifications` MODIFY `from_user_id` char(36) NULL");
        } else {
            // Si no existe, crearla directamente como UUID
            Schema::table('notifications', function (Blueprint $table) {
                $table->uuid('from_user_id')->nullable()->after('user_id');
            });
        }

        // Crear FK a users(id) UUID
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }
        // Quitar FK
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['from_user_id']);
            });
        } catch (\Throwable $e) {}
        // (Opcional) revertir tipo - no lo hacemos para no perder compatibilidad
    }
};
