<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ajustar foreign key container_id si apunta a la tabla incorrecta
        if (Schema::hasTable('organization_container_folders') && Schema::hasTable('meeting_content_containers')) {
            // Inspeccionar llaves foráneas existentes sobre container_id
            $fkName = null;
            $constraints = DB::select("SELECT CONSTRAINT_NAME as name, REFERENCED_TABLE_NAME as ref_table FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='organization_container_folders' AND COLUMN_NAME='container_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            foreach ($constraints as $c) {
                if ($c->ref_table !== 'meeting_content_containers') {
                    $fkName = $c->name;
                    break;
                } elseif ($c->ref_table === 'meeting_content_containers') {
                    // Ya correcto, nada que hacer
                    return;
                }
            }
            if ($fkName) {
                Schema::table('organization_container_folders', function (Blueprint $table) use ($fkName) {
                    $table->dropForeign([$fkName]);
                });
                // MySQL requiere sintaxis exacta para drop foreign key, fallback manual
                try { DB::statement("ALTER TABLE `organization_container_folders` DROP FOREIGN KEY `{$fkName}`"); } catch (Throwable $e) {}
                try { DB::statement("ALTER TABLE `organization_container_folders` ADD CONSTRAINT `org_container_folders_container_fk` FOREIGN KEY (`container_id`) REFERENCES `meeting_content_containers`(`id`) ON DELETE CASCADE"); } catch (Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        // No revertimos; opcionalmente se podría intentar restaurar, pero no es necesario.
    }
};
