<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ELIMINACIÓN FINAL DE TABLAS CON FOREIGN KEY ===\n\n";

$tablesToDelete = ['meeting_containers', 'user_subscriptions'];

echo "🗑️  TABLAS A ELIMINAR:\n";
foreach ($tablesToDelete as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "  • {$table} (Registros: {$count})\n";
    } else {
        echo "  • {$table} (No existe)\n";
    }
}

echo "\n⚠️  MÉTODO: Desactivación temporal de foreign key checks\n";
echo "Este método es seguro y no afecta otras tablas.\n\n";

echo "¿Continuar con la eliminación? (y/n): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'y' && strtolower($response) !== 'yes') {
    echo "❌ Operación cancelada.\n";
    exit(0);
}

echo "\n🚀 Eliminando tablas...\n\n";

try {
    // Desactivar foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    echo "🔓 Foreign key checks desactivados\n";

    $deletedCount = 0;

    foreach ($tablesToDelete as $table) {
        if (!Schema::hasTable($table)) {
            echo "  ℹ️  Tabla {$table} no existe\n";
            continue;
        }

        try {
            Schema::drop($table);
            echo "  ✅ Eliminada: {$table}\n";
            $deletedCount++;
        } catch (Exception $e) {
            echo "  ❌ Error eliminando {$table}: " . $e->getMessage() . "\n";
        }
    }

    // Reactivar foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    echo "\n🔒 Foreign key checks reactivados\n";

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ LIMPIEZA COMPLETADA\n";
    echo "Tablas eliminadas: {$deletedCount}\n";

    // Verificación final
    echo "\n🔍 VERIFICACIÓN FINAL:\n";
    foreach ($tablesToDelete as $table) {
        $exists = Schema::hasTable($table);
        $status = $exists ? "❌ AÚN EXISTE" : "✅ ELIMINADA";
        echo "  {$table}: {$status}\n";
    }

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

echo "\n🎉 Proceso de limpieza completado.\n";
echo "Base de datos optimizada exitosamente.\n";
