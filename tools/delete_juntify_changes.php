<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ELIMINACIÓN DE TABLA JUNTIFY_CHANGES ===\n\n";

$tableToDelete = 'juntify_changes';

if (!Schema::hasTable($tableToDelete)) {
    echo "❌ La tabla {$tableToDelete} no existe.\n";
    exit(0);
}

// Mostrar datos que se van a eliminar
echo "📊 DATOS A ELIMINAR:\n";
echo str_repeat("-", 40) . "\n";

$records = DB::table($tableToDelete)->get();
$count = $records->count();

echo "Tabla: {$tableToDelete}\n";
echo "Registros: {$count}\n\n";

echo "📝 CONTENIDO QUE SE ELIMINARÁ:\n";
echo str_repeat("-", 40) . "\n";

foreach ($records as $record) {
    echo "• Versión: {$record->version}\n";
    echo "  Fecha: {$record->change_date}\n";
    echo "  Descripción: " . substr($record->description, 0, 100) . "...\n\n";
}

echo str_repeat("=", 50) . "\n";
echo "⚠️  ATENCIÓN: Esta acción eliminará la tabla y TODOS sus datos.\n";
echo "No se podrá recuperar la información histórica de versiones.\n\n";

echo "¿CONFIRMAR ELIMINACIÓN? (escriba 'ELIMINAR' para proceder): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if ($response !== 'ELIMINAR') {
    echo "❌ Operación cancelada. La tabla se mantiene segura.\n";
    exit(0);
}

echo "\n🚀 Eliminando tabla juntify_changes...\n";

try {
    Schema::drop($tableToDelete);
    echo "✅ Tabla '{$tableToDelete}' eliminada exitosamente.\n";
    echo "   Registros eliminados: {$count}\n";

    // Verificación final
    $exists = Schema::hasTable($tableToDelete);
    $status = $exists ? "❌ AÚN EXISTE" : "✅ ELIMINADA CORRECTAMENTE";
    echo "\n🔍 VERIFICACIÓN: {$tableToDelete} - {$status}\n";

} catch (Exception $e) {
    echo "❌ Error eliminando la tabla: " . $e->getMessage() . "\n";
}

echo "\n🎉 Proceso completado.\n";
echo "Base de datos optimizada - tabla histórica eliminada.\n";
