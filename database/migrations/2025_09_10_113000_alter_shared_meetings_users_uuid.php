<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_meetings')) {
            return;
        }

        // Comprobar si existen las llaves foráneas antes de intentar eliminarlas
        $dbName = DB::getDatabaseName();
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shared_meetings' AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$dbName]
        );
        $constraintNames = array_map(fn($r) => $r->CONSTRAINT_NAME, $constraints);

        foreach ([
            'shared_meetings_shared_by_foreign',
            'shared_meetings_shared_with_foreign',
        ] as $fkName) {
            if (in_array($fkName, $constraintNames, true)) {
                DB::statement("ALTER TABLE `shared_meetings` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        // Cambiar tipo de columnas a UUID (CHAR(36)) mediante SQL directo para evitar problemas con DBAL
        if (Schema::hasColumn('shared_meetings', 'shared_by')) {
            DB::statement("ALTER TABLE `shared_meetings` MODIFY `shared_by` char(36) NOT NULL");
        }
        if (Schema::hasColumn('shared_meetings', 'shared_with')) {
            DB::statement("ALTER TABLE `shared_meetings` MODIFY `shared_with` char(36) NOT NULL");
        }

        // Volver a crear las claves foráneas
        Schema::table('shared_meetings', function (Blueprint $table) {
            if (Schema::hasColumn('shared_meetings', 'shared_by')) {
                $table->foreign('shared_by')->references('id')->on('users')->onDelete('cascade');
            }
            if (Schema::hasColumn('shared_meetings', 'shared_with')) {
                $table->foreign('shared_with')->references('id')->on('users')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shared_meetings')) {
            return;
        }
        // Nota: No regresamos a BIGINT para evitar pérdida de datos; solo quitamos FKs
        Schema::table('shared_meetings', function (Blueprint $table) {
            try { $table->dropForeign('shared_meetings_shared_by_foreign'); } catch (\Throwable $e) {}
            try { $table->dropForeign('shared_meetings_shared_with_foreign'); } catch (\Throwable $e) {}
        });
    }
};
