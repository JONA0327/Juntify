<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LIMPIEZA FINAL: TABLAS CON FOREIGN KEY CONSTRAINTS ===\n\n";

$remainingTables = ['meeting_containers', 'user_subscriptions'];

foreach ($remainingTables as $table) {
    echo "🔍 ANALIZANDO: {$table}\n";
    echo str_repeat("-", 40) . "\n";

    if (!Schema::hasTable($table)) {
        echo "  ℹ️  Tabla {$table} no existe\n\n";
        continue;
    }

    try {
        // Contar registros
        $count = DB::table($table)->count();
        echo "  📊 Registros: {$count}\n";

        // Obtener foreign keys que referencian esta tabla
        $foreignKeys = DB::select("
            SELECT
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE
                CONSTRAINT_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = ?
        ", [$table]);

        echo "  🔗 Foreign keys que referencian esta tabla: " . count($foreignKeys) . "\n";

        if (count($foreignKeys) > 0) {
            echo "  📝 Referencias encontradas:\n";
            foreach ($foreignKeys as $fk) {
                $referencingCount = DB::table($fk->TABLE_NAME)->count();
                echo "    - {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME} (Constraint: {$fk->CONSTRAINT_NAME}) [Registros: {$referencingCount}]\n";
            }
        }

        // Obtener foreign keys de esta tabla hacia otras
        $outgoingForeignKeys = DB::select("
            SELECT
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE
                CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table]);

        echo "  ➡️  Foreign keys de esta tabla: " . count($outgoingForeignKeys) . "\n";

        if (count($outgoingForeignKeys) > 0) {
            echo "  📝 Foreign keys salientes:\n";
            foreach ($outgoingForeignKeys as $fk) {
                echo "    - {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} → {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME} (Constraint: {$fk->CONSTRAINT_NAME})\n";
            }
        }

    } catch (Exception $e) {
        echo "  ❌ Error analizando: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "🛠️  ESTRATEGIA DE ELIMINACIÓN\n";
echo str_repeat("=", 60) . "\n";

echo "Para eliminar las tablas con foreign key constraints de forma segura:\n\n";
echo "1️⃣ OPCIÓN 1: Eliminar constraints primero, luego la tabla\n";
echo "2️⃣ OPCIÓN 2: Eliminar en cascada (si las tablas que referencian también son innecesarias)\n";
echo "3️⃣ OPCIÓN 3: Usar DROP TABLE con CASCADE (MySQL no lo soporta nativamente)\n";
echo "4️⃣ OPCIÓN 4: Desactivar foreign key checks temporalmente\n\n";

echo "¿Qué método prefieres?\n";
echo "1 - Eliminar constraints y luego tablas\n";
echo "2 - Desactivar foreign key checks temporalmente\n";
echo "3 - Analizar manualmente cada constraint\n";
echo "Ingresa tu opción (1, 2, o 3): ";

$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));
fclose($handle);

switch ($choice) {
    case '1':
        eliminateWithConstraintRemoval($remainingTables);
        break;
    case '2':
        eliminateWithDisabledChecks($remainingTables);
        break;
    case '3':
        echo "\n📋 Para análisis manual, revisa los foreign keys mostrados arriba.\n";
        echo "Puedes eliminar manualmente los constraints específicos y luego las tablas.\n";
        break;
    default:
        echo "\n❌ Opción no válida. Operación cancelada.\n";
}

function eliminateWithConstraintRemoval($tables) {
    echo "\n🚀 ELIMINANDO CON REMOCIÓN DE CONSTRAINTS\n";
    echo str_repeat("-", 50) . "\n";

    foreach ($tables as $table) {
        if (!Schema::hasTable($table)) {
            continue;
        }

        echo "📋 Procesando: {$table}\n";

        try {
            // Obtener foreign keys que referencian esta tabla
            $foreignKeys = DB::select("
                SELECT
                    CONSTRAINT_NAME,
                    TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE
                    CONSTRAINT_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_NAME = ?
                    AND CONSTRAINT_NAME != 'PRIMARY'
            ", [$table]);

            // Eliminar foreign key constraints
            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                    echo "  ✅ Constraint eliminado: {$fk->TABLE_NAME}.{$fk->CONSTRAINT_NAME}\n";
                } catch (Exception $e) {
                    echo "  ⚠️  Error eliminando constraint {$fk->CONSTRAINT_NAME}: " . $e->getMessage() . "\n";
                }
            }

            // Ahora eliminar la tabla
            Schema::drop($table);
            echo "  ✅ Tabla eliminada: {$table}\n";

        } catch (Exception $e) {
            echo "  ❌ Error procesando {$table}: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }
}

function eliminateWithDisabledChecks($tables) {
    echo "\n🚀 ELIMINANDO CON FOREIGN KEY CHECKS DESACTIVADOS\n";
    echo str_repeat("-", 50) . "\n";

    try {
        // Desactivar foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        echo "🔓 Foreign key checks desactivados\n\n";

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                echo "  ℹ️  Tabla {$table} no existe\n";
                continue;
            }

            try {
                Schema::drop($table);
                echo "  ✅ Tabla eliminada: {$table}\n";
            } catch (Exception $e) {
                echo "  ❌ Error eliminando {$table}: " . $e->getMessage() . "\n";
            }
        }

        // Reactivar foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        echo "\n🔒 Foreign key checks reactivados\n";

    } catch (Exception $e) {
        echo "❌ Error en el proceso: " . $e->getMessage() . "\n";

        // Asegurar que se reactiven los checks
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            echo "🔒 Foreign key checks reactivados (recovery)\n";
        } catch (Exception $recovery) {
            echo "⚠️  Error reactivando foreign key checks: " . $recovery->getMessage() . "\n";
        }
    }
}

echo "\n🎉 Proceso completado.\n";
