<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICACIÓN DE BASE DE DATOS ===\n\n";

// Mostrar configuración
$panelsDb = config('database.connections.juntify_panels.database');
echo "BD configurada: $panelsDb\n\n";

// Probar conexión
try {
    $tables = DB::connection('juntify_panels')->select('SHOW TABLES');
    echo "✅ Conexión exitosa\n";
    echo "Tablas encontradas: " . count($tables) . "\n\n";
    
    // Buscar tabla google_tokens
    $found = false;
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if (stripos($tableName, 'google') !== false || stripos($tableName, 'token') !== false) {
            echo "Tabla relacionada: $tableName\n";
            $found = true;
        }
    }
    
    if (!$found) {
        echo "\n❌ No se encontró tabla google_tokens\n";
        echo "\nPrimeras 10 tablas:\n";
        for ($i = 0; $i < min(10, count($tables)); $i++) {
            $tableName = array_values((array)$tables[$i])[0];
            echo "- $tableName\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
