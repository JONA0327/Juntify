<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ANÁLISIS EXHAUSTIVO DE TABLAS VACÍAS O SIN USO ===\n\n";

// Obtener todas las tablas actuales
$allTables = DB::select('SHOW TABLES');
$tableColumn = 'Tables_in_' . env('DB_DATABASE');
$currentTables = array_map(function($table) use ($tableColumn) {
    return $table->$tableColumn;
}, $allTables);

echo "📊 TOTAL DE TABLAS ACTUALES: " . count($currentTables) . "\n\n";

// Tablas CRÍTICAS que NUNCA se deben tocar
$criticalTables = [
    'users', 'migrations', 'password_reset_tokens', 'sessions', 'personal_access_tokens',
    'google_tokens', 'folders', 'subfolders', 'transcriptions_laravel', 'meetings',
    'organizations', 'organization_user', 'plans', 'payments', 'mercado_pago_payments',
    'groups', 'group_user', 'tasks_laravel', 'notifications', 'contacts',
    'pending_recordings', 'user_permissions', 'meeting_content_containers',
    'permissions', 'user_panel_miembros', 'meeting_content_relations'
];

$emptyTables = [];
$tablesWithDataButUnused = [];
$activeTablesWithData = [];

echo "🔍 ANALIZANDO CADA TABLA...\n";
echo str_repeat("=", 70) . "\n";

foreach ($currentTables as $table) {
    if (in_array($table, $criticalTables)) {
        continue; // Skip critical tables
    }

    try {
        $count = DB::table($table)->count();

        echo "\n📋 {$table}\n";
        echo str_repeat("-", 35) . "\n";
        echo "  📊 Registros: {$count}\n";

        // Buscar referencias en código
        $hasCodeReferences = hasCodeReferences($table);
        echo "  🔍 Referencias en código: " . ($hasCodeReferences ? "SÍ" : "NO") . "\n";

        // Verificar modelo Eloquent
        $hasModel = hasEloquentModel($table);
        echo "  🏗️  Modelo Eloquent: " . ($hasModel ? "SÍ" : "NO") . "\n";

        // Verificar foreign keys
        $hasForeignKeys = hasForeignKeys($table);
        echo "  🔗 Foreign Keys: " . ($hasForeignKeys ? "SÍ" : "NO") . "\n";

        // Verificar uso en vistas
        $hasViewReferences = hasViewReferences($table);
        echo "  🎨 En vistas/JS: " . ($hasViewReferences ? "SÍ" : "NO") . "\n";

        // Determinar categoría
        if ($count == 0) {
            if (!$hasCodeReferences && !$hasModel && !$hasForeignKeys && !$hasViewReferences) {
                $emptyTables[] = $table;
                echo "  🗑️  CANDIDATA: Tabla vacía sin uso\n";
            } else {
                echo "  ✅ CONSERVAR: Tabla vacía pero con referencias\n";
            }
        } else {
            if (!$hasCodeReferences && !$hasModel && !$hasForeignKeys && !$hasViewReferences) {
                $tablesWithDataButUnused[] = [
                    'table' => $table,
                    'records' => $count
                ];
                echo "  🗑️  CANDIDATA: Con datos pero sin uso\n";
            } else {
                $activeTablesWithData[] = $table;
                echo "  ✅ CONSERVAR: En uso activo\n";
            }
        }

    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 RESUMEN COMPLETO\n";
echo str_repeat("=", 70) . "\n";

echo "🗑️  TABLAS VACÍAS SIN USO: " . count($emptyTables) . "\n";
if (!empty($emptyTables)) {
    foreach ($emptyTables as $table) {
        echo "   • {$table}\n";
    }
}

echo "\n🗑️  TABLAS CON DATOS PERO SIN USO: " . count($tablesWithDataButUnused) . "\n";
if (!empty($tablesWithDataButUnused)) {
    foreach ($tablesWithDataButUnused as $tableData) {
        echo "   • {$tableData['table']} ({$tableData['records']} registros)\n";
    }
}

echo "\n✅ TABLAS ACTIVAS CONSERVADAS: " . count($activeTablesWithData) . "\n";
echo "✅ TABLAS CRÍTICAS PROTEGIDAS: " . count($criticalTables) . "\n";

$totalCandidates = count($emptyTables) + count($tablesWithDataButUnused);
echo "\n🎯 TOTAL DE CANDIDATAS PARA ELIMINACIÓN: {$totalCandidates}\n";

if ($totalCandidates > 0) {
    echo "\n¿Generar script para eliminar todas estas tablas? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
        generateMassCleanupScript($emptyTables, $tablesWithDataButUnused);
    }
} else {
    echo "\n🎉 ¡Excelente! No hay más tablas innecesarias para eliminar.\n";
}

function hasCodeReferences($table) {
    // Buscar en archivos PHP
    $searchPaths = [
        __DIR__ . '/../app',
        __DIR__ . '/../routes',
        __DIR__ . '/../config'
    ];

    foreach ($searchPaths as $path) {
        if (searchInDirectory($path, $table)) {
            return true;
        }
    }
    return false;
}

function hasEloquentModel($table) {
    $modelNames = [
        studly_case($table),
        studly_case(str_singular($table)),
        ucfirst(camel_case($table)),
        ucfirst(camel_case(str_singular($table)))
    ];

    foreach ($modelNames as $modelName) {
        if (file_exists(__DIR__ . "/../app/Models/{$modelName}.php")) {
            return true;
        }
    }
    return false;
}

function hasForeignKeys($table) {
    try {
        $foreignKeys = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE
                CONSTRAINT_SCHEMA = DATABASE()
                AND (REFERENCED_TABLE_NAME = ? OR TABLE_NAME = ?)
                AND CONSTRAINT_NAME != 'PRIMARY'
        ", [$table, $table]);

        return ($foreignKeys[0]->count ?? 0) > 0;
    } catch (Exception $e) {
        return false;
    }
}

function hasViewReferences($table) {
    $searchPaths = [
        __DIR__ . '/../resources/views',
        __DIR__ . '/../resources/js'
    ];

    foreach ($searchPaths as $path) {
        if (searchInDirectory($path, $table)) {
            return true;
        }
    }
    return false;
}

function searchInDirectory($directory, $searchTerm) {
    if (!is_dir($directory)) return false;

    $command = "findstr /R /I /S \"{$searchTerm}\" \"{$directory}\\*\" 2>nul";
    $output = [];
    exec($command, $output);

    return count($output) > 0;
}

function studly_case($value) {
    return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
}

function camel_case($value) {
    return lcfirst(studly_case($value));
}

function str_singular($value) {
    if (substr($value, -3) === 'ies') {
        return substr($value, 0, -3) . 'y';
    } elseif (substr($value, -1) === 's') {
        return substr($value, 0, -1);
    }
    return $value;
}

function generateMassCleanupScript($emptyTables, $tablesWithData) {
    $allTables = array_merge($emptyTables, array_column($tablesWithData, 'table'));

    $scriptContent = "<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

\$app = require_once __DIR__ . '/../bootstrap/app.php';
\$kernel = \$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\$kernel->bootstrap();

echo \"=== LIMPIEZA MASIVA DE TABLAS INNECESARIAS ===\\n\\n\";

\$tablesToDelete = [
";

    foreach ($emptyTables as $table) {
        $scriptContent .= "    '{$table}', // Tabla vacía\n";
    }

    foreach ($tablesWithData as $tableData) {
        $scriptContent .= "    '{$tableData['table']}', // {$tableData['records']} registros sin uso\n";
    }

    $scriptContent .= "];

echo \"⚠️  SE ELIMINARÁN \" . count(\$tablesToDelete) . \" TABLAS\\n\\n\";

echo \"📋 TABLAS A ELIMINAR:\\n\";
\$totalRecords = 0;
foreach (\$tablesToDelete as \$table) {
    if (Schema::hasTable(\$table)) {
        \$count = DB::table(\$table)->count();
        \$totalRecords += \$count;
        echo \"  • {\$table} ({\$count} registros)\\n\";
    }
}

echo \"\\nTotal de registros a eliminar: {\$totalRecords}\\n\\n\";

echo \"¿CONFIRMAR ELIMINACIÓN MASIVA? (escriba 'ELIMINAR TODO' para proceder): \";
\$handle = fopen(\"php://stdin\", \"r\");
\$response = trim(fgets(\$handle));
fclose(\$handle);

if (\$response !== 'ELIMINAR TODO') {
    echo \"❌ Operación cancelada.\\n\";
    exit(0);
}

echo \"\\n🚀 Iniciando eliminación masiva...\\n\\n\";

// Desactivar foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS = 0');

\$deleted = 0;
\$totalDeleted = 0;

foreach (\$tablesToDelete as \$table) {
    try {
        if (Schema::hasTable(\$table)) {
            \$count = DB::table(\$table)->count();
            Schema::drop(\$table);
            echo \"✅ {\$table} ({\$count} registros)\\n\";
            \$deleted++;
            \$totalDeleted += \$count;
        }
    } catch (Exception \$e) {
        echo \"❌ Error con {\$table}: \" . \$e->getMessage() . \"\\n\";
    }
}

// Reactivar foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS = 1');

echo \"\\n\" . str_repeat(\"=\", 50) . \"\\n\";
echo \"🎉 LIMPIEZA MASIVA COMPLETADA\\n\";
echo \"Tablas eliminadas: {\$deleted}\\n\";
echo \"Registros eliminados: {\$totalDeleted}\\n\";
echo \"\\n✨ Base de datos completamente optimizada.\\n\";
";

    $scriptPath = __DIR__ . '/mass_cleanup_tables.php';
    file_put_contents($scriptPath, $scriptContent);
    echo "\n✅ Script generado: {$scriptPath}\n";
    echo "Para ejecutar: php tools/mass_cleanup_tables.php\n";
}

echo "\n🎉 Análisis exhaustivo completado.\n";
