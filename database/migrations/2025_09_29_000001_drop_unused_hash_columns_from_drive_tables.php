<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tablas y columnas a eliminar si existen
        $tables = [
            'organization_folders' => ['google_id_enc', 'google_id_hash'],
            'organization_subfolders' => ['google_id_enc', 'google_id_hash'],
            'folders' => ['google_id_enc', 'google_id_hash'],
            'subfolders' => ['google_id_enc', 'google_id_hash'],
        ];

        foreach ($tables as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $tb) use ($table, $columns) {
                foreach ($columns as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $tb->dropColumn($col);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Restaurar columnas (sin índices) por compatibilidad de rollback
        $tables = [
            'organization_folders',
            'organization_subfolders',
            'folders',
            'subfolders',
        ];
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $tb) use ($table) {
                // Campos simples string nullable (recreación mínima)
                if (!Schema::hasColumn($table, 'google_id_enc')) {
                    $tb->string('google_id_enc')->nullable();
                }
                if (!Schema::hasColumn($table, 'google_id_hash')) {
                    $tb->string('google_id_hash')->nullable();
                }
            });
        }
    }
};
