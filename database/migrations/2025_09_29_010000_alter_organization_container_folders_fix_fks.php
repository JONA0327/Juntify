<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('organization_container_folders')) return;

        // Verificar tipo actual de container_id; si ya es BIGINT UNSIGNED y tiene FK correcta, no hacemos nada
        $columnInfo = DB::select("SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='organization_container_folders' AND COLUMN_NAME='container_id'");
        $needsAlter = true;
        if ($columnInfo) {
            $col = $columnInfo[0];
            if (stripos($col->COLUMN_TYPE, 'bigint') !== false) {
                $needsAlter = false; // ya es bigint
            }
        }

        if ($needsAlter) {
            try { DB::statement('ALTER TABLE organization_container_folders MODIFY container_id BIGINT UNSIGNED'); } catch (Throwable $e) {}
        }

        // Asegurar foreign key container_id -> meeting_content_containers(id)
        $fkOk = DB::select("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='organization_container_folders' AND COLUMN_NAME='container_id' AND REFERENCED_TABLE_NAME='meeting_content_containers'");
        if (!$fkOk) {
            // Intentar eliminar cualquier FK previa sobre container_id y recrear
            $constraints = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='organization_container_folders' AND COLUMN_NAME='container_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            foreach ($constraints as $c) {
                try { DB::statement("ALTER TABLE `organization_container_folders` DROP FOREIGN KEY `{$c->CONSTRAINT_NAME}`"); } catch (Throwable $e) {}
            }
            try { DB::statement("ALTER TABLE `organization_container_folders` ADD CONSTRAINT `org_container_folders_container_fk` FOREIGN KEY (`container_id`) REFERENCES `meeting_content_containers`(`id`) ON DELETE CASCADE"); } catch (Throwable $e) {}
        }
    }

    public function down(): void
    {
        // No reversión específica; dejamos cambios.
    }
};
