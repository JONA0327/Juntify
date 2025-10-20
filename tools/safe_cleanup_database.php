<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LIMPIEZA SEGURA DE BASE DE DATOS ACTUALIZADA ===\n\n";

// TABLAS REALMENTE SEGURAS PARA ELIMINAR (después del análisis profundo)
$tablesToDelete = [
    'orders'           // Tabla vacía sin usar - ÚNICA TABLA A ELIMINAR
    // user_permissions - CONSERVADA por solicitud del usuario
];

// TABLAS QUE NO SE DEBEN ELIMINAR (CONSERVAR)
$tablesToPreserve = [
    'pending_recordings',  // SE USA ACTIVAMENTE en el sistema de procesamiento de audios
    'user_permissions',    // CONSERVADA por solicitud del usuario
    // Todas las demás tablas del sistema se conservan automáticamente
];

echo "⚠️  IMPORTANTE: Se ha actualizado el análisis después de verificar el uso real.\n";
echo "La tabla 'pending_recordings' se CONSERVARÁ porque se usa activamente en el código.\n\n";

echo "🗑️  TABLAS A ELIMINAR (confirmadas como seguras):\n";
echo str_repeat("-", 50) . "\n";
foreach ($tablesToDelete as $table) {
    try {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            echo "  • {$table} (Registros: {$count})\n";
        } else {
            echo "  • {$table} (No existe)\n";
        }
    } catch (Exception $e) {
        echo "  • {$table} (Error: {$e->getMessage()})\n";
    }
}

echo "\n✅ TABLAS CONSERVADAS (en uso):\n";
echo str_repeat("-", 50) . "\n";
foreach ($tablesToPreserve as $table) {
    try {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            echo "  • {$table} (Registros: {$count}) - SE MANTIENE\n";
        }
    } catch (Exception $e) {
        echo "  • {$table} (Error verificando)\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "¿Continuar con la eliminación de las tablas seguras? (y/n): ";

$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'y' && strtolower($response) !== 'yes') {
    echo "❌ Operación cancelada por el usuario.\n";
    exit(0);
}

echo "\n🚀 Iniciando limpieza segura...\n\n";

// Eliminar solo las tablas realmente seguras
$deletedTables = 0;
foreach ($tablesToDelete as $table) {
    try {
        if (Schema::hasTable($table)) {
            Schema::drop($table);
            echo "✅ Tabla eliminada: {$table}\n";
            $deletedTables++;
        } else {
            echo "ℹ️  Tabla {$table} no existe\n";
        }
    } catch (Exception $e) {
        echo "❌ Error eliminando tabla {$table}: " . $e->getMessage() . "\n";
    }
}

// NO eliminamos migraciones de pending_recordings ya que se usa activamente
echo "\n📄 Nota: Las migraciones de 'pending_recordings' se conservan porque la tabla está en uso.\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ LIMPIEZA COMPLETADA\n";
echo "Tablas eliminadas: {$deletedTables}\n";
echo "Tablas conservadas correctamente: " . count($tablesToPreserve) . " + todas las demás del sistema\n";

// Verificación final
echo "\n🔍 VERIFICACIÓN FINAL:\n";
echo str_repeat("-", 30) . "\n";

foreach ($tablesToDelete as $table) {
    $exists = Schema::hasTable($table);
    $status = $exists ? "❌ AÚN EXISTE" : "✅ ELIMINADA";
    echo "  {$table}: {$status}\n";
}

foreach ($tablesToPreserve as $table) {
    $exists = Schema::hasTable($table);
    $status = $exists ? "✅ CONSERVADA" : "❌ NO EXISTE";
    echo "  {$table}: {$status}\n";
}

echo "\n🎉 La base de datos ha sido limpiada de forma segura.\n";
echo "Todas las tablas importantes del sistema de empresas, planes y audios se mantienen intactas.\n";
