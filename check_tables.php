<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Verificando tablas relacionadas con pagos...\n\n";

    // Obtener todas las tablas
    $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

    $paymentTables = array_filter($tables, function($table) {
        return strpos($table, 'plan') !== false ||
               strpos($table, 'payment') !== false ||
               strpos($table, 'purchase') !== false ||
               strpos($table, 'mercado') !== false;
    });

    echo "Tablas relacionadas con pagos encontradas:\n";
    foreach ($paymentTables as $table) {
        echo "- $table\n";

        // Verificar si la tabla tiene datos
        try {
            $count = DB::table($table)->count();
            echo "  Registros: $count\n";
        } catch (Exception $e) {
            echo "  Error al contar registros: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    // Verificar específicamente las tablas que necesitamos
    $requiredTables = ['plan_purchases', 'user_plans', 'plans', 'mercado_pago_payments'];

    echo "\nVerificación de tablas requeridas:\n";
    foreach ($requiredTables as $table) {
        if (Schema::hasTable($table)) {
            echo "✅ $table - Existe\n";
            try {
                $count = DB::table($table)->count();
                echo "   Registros: $count\n";
            } catch (Exception $e) {
                echo "   Error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ $table - NO EXISTE\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
