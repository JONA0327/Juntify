<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ANÁLISIS DE TABLAS CON DATOS PERO SIN USO REAL ===\n\n";

// Obtener todas las tablas
$allTables = DB::select('SHOW TABLES');
$tableColumn = 'Tables_in_' . env('DB_DATABASE');
$currentTables = array_map(function($table) use ($tableColumn) {
    return $table->$tableColumn;
}, $allTables);

// Tablas que definitivamente NO se deben tocar
$protectedTables = [
    'users', 'google_tokens', 'folders', 'subfolders', 'transcriptions_laravel',
    'meeting_content_containers', 'pending_recordings', 'user_permissions',
    'organizations', 'organization_user', 'plans', 'payments', 'mercado_pago_payments',
    'meetings', 'shared_meetings', 'groups', 'group_user', 'tasks_laravel',
    'notifications', 'migrations', 'failed_jobs', 'jobs', 'sessions',
    'personal_access_tokens', 'password_reset_tokens', 'contacts',
    'permissions', 'user_panel_miembros', 'meeting_content_relations'
];

echo "🔍 ANALIZANDO TABLAS CON DATOS PERO POSIBLE DESUSO\n";
echo str_repeat("=", 60) . "\n";

$candidatesWithData = [];

foreach ($currentTables as $table) {
    if (in_array($table, $protectedTables)) {
        continue; // Skip protected tables
    }

    try {
        $count = DB::table($table)->count();

        if ($count > 0) {
            echo "\n📋 TABLA: {$table}\n";
            echo str_repeat("-", 30) . "\n";
            echo "  📊 Registros: {$count}\n";

            // Buscar referencias en código PHP
            $phpReferences = searchCodeReferences($table);
            echo "  🔍 Referencias en código PHP: {$phpReferences['count']}\n";

            if ($phpReferences['count'] > 0) {
                echo "  📝 Archivos encontrados:\n";
                foreach (array_slice($phpReferences['files'], 0, 3) as $file) {
                    echo "    - {$file}\n";
                }
                if (count($phpReferences['files']) > 3) {
                    echo "    ... y " . (count($phpReferences['files']) - 3) . " más\n";
                }
            }

            // Buscar en vistas Blade
            $bladeReferences = searchBladeReferences($table);
            echo "  🎨 Referencias en vistas Blade: {$bladeReferences}\n";

            // Buscar en JavaScript/Vue
            $jsReferences = searchJsReferences($table);
            echo "  ⚡ Referencias en JS/Vue: {$jsReferences}\n";

            // Verificar si hay modelos Eloquent
            $modelExists = file_exists(__DIR__ . "/../app/Models/" . studly_case($table) . ".php") ||
                          file_exists(__DIR__ . "/../app/Models/" . studly_case(str_singular($table)) . ".php");
            echo "  🏗️  Modelo Eloquent: " . ($modelExists ? "SÍ" : "NO") . "\n";

            // Verificar foreign keys
            $foreignKeys = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE
                    CONSTRAINT_SCHEMA = DATABASE()
                    AND (REFERENCED_TABLE_NAME = ? OR TABLE_NAME = ?)
                    AND CONSTRAINT_NAME != 'PRIMARY'
            ", [$table, $table]);

            $fkCount = $foreignKeys[0]->count ?? 0;
            echo "  🔗 Foreign Keys: {$fkCount}\n";

            // Determinar si es candidata para eliminación
            $totalReferences = $phpReferences['count'] + $bladeReferences + $jsReferences;
            $isCandidate = $totalReferences == 0 && !$modelExists && $fkCount == 0;

            if ($isCandidate) {
                $candidatesWithData[] = [
                    'table' => $table,
                    'records' => $count,
                    'references' => $totalReferences,
                    'model' => $modelExists,
                    'foreign_keys' => $fkCount
                ];
                echo "  🗑️  CANDIDATA PARA ELIMINACIÓN (tiene datos pero no se usa)\n";
            } else {
                echo "  ✅ MANTENER (en uso o tiene dependencias)\n";
            }
        }

    } catch (Exception $e) {
        echo "  ❌ Error analizando {$table}: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RESUMEN DE CANDIDATAS CON DATOS\n";
echo str_repeat("=", 60) . "\n";

if (!empty($candidatesWithData)) {
    echo "🗑️  TABLAS CON DATOS PERO SIN USO DETECTADO:\n";
    echo str_repeat("-", 50) . "\n";

    $totalRecords = 0;
    foreach ($candidatesWithData as $candidate) {
        echo sprintf(
            "  • %-25s (%d registros)\n",
            $candidate['table'],
            $candidate['records']
        );
        $totalRecords += $candidate['records'];
    }

    echo "\nTotal de tablas: " . count($candidatesWithData) . "\n";
    echo "Total de registros a eliminar: {$totalRecords}\n";

    echo "\n⚠️  ATENCIÓN: Estas tablas tienen datos pero no parecen usarse.\n";
    echo "Revisa manualmente antes de eliminar para asegurarte.\n";

    echo "\n¿Generar script de eliminación para estas tablas? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
        generateDataCleanupScript($candidatesWithData);
    }

} else {
    echo "✅ No se encontraron tablas con datos sin uso.\n";
    echo "Todas las tablas con datos parecen estar en uso activo.\n";
}

function searchCodeReferences($table) {
    $baseDir = __DIR__ . '/..';
    $searchPaths = [
        'app/**/*.php',
        'routes/*.php',
        'config/*.php'
    ];

    $files = [];
    $count = 0;

    foreach ($searchPaths as $path) {
        $fullPath = $baseDir . '/' . str_replace('**/', '', $path);
        $command = "findstr /R /I /S \"{$table}\" \"{$fullPath}\" 2>nul";

        $output = [];
        exec($command, $output);

        foreach ($output as $line) {
            if (strpos($line, ':') !== false) {
                $filePart = explode(':', $line)[0];
                if (!in_array($filePart, $files)) {
                    $files[] = $filePart;
                    $count++;
                }
            }
        }
    }

    return ['count' => $count, 'files' => $files];
}

function searchBladeReferences($table) {
    $baseDir = __DIR__ . '/..';
    $command = "findstr /R /I /S \"{$table}\" \"{$baseDir}\\resources\\views\\*.blade.php\" 2>nul";

    $output = [];
    exec($command, $output);

    return count($output);
}

function searchJsReferences($table) {
    $baseDir = __DIR__ . '/..';
    $searchPaths = [
        'resources/js/**/*.js',
        'resources/js/**/*.vue',
        'public/js/**/*.js'
    ];

    $count = 0;
    foreach ($searchPaths as $path) {
        $fullPath = $baseDir . '/' . str_replace('**/', '', dirname($path));
        $command = "findstr /R /I /S \"{$table}\" \"{$fullPath}\" 2>nul";

        $output = [];
        exec($command, $output);
        $count += count($output);
    }

    return $count;
}

function studly_case($value) {
    return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
}

function str_singular($value) {
    // Simple singularization
    if (substr($value, -3) === 'ies') {
        return substr($value, 0, -3) . 'y';
    } elseif (substr($value, -1) === 's') {
        return substr($value, 0, -1);
    }
    return $value;
}

function generateDataCleanupScript($candidates) {
    $scriptContent = "<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

// Bootstrap Laravel
\$app = require_once __DIR__ . '/../bootstrap/app.php';
\$kernel = \$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\$kernel->bootstrap();

echo \"=== ELIMINACIÓN DE TABLAS CON DATOS PERO SIN USO ===\\n\\n\";

\$tablesToClean = [
";

    foreach ($candidates as $candidate) {
        $scriptContent .= "    '{$candidate['table']}', // {$candidate['records']} registros\n";
    }

    $scriptContent .= "];

echo \"⚠️  ATENCIÓN: Estas tablas contienen datos pero no parecen usarse.\\n\";
echo \"Se eliminarán tanto las tablas como todos sus datos.\\n\\n\";

echo \"🗑️  TABLAS Y DATOS A ELIMINAR:\\n\";
foreach (\$tablesToClean as \$table) {
    if (Schema::hasTable(\$table)) {
        \$count = DB::table(\$table)->count();
        echo \"  • {\$table} ({\$count} registros)\\n\";
    }
}

echo \"\\n¿CONFIRMAR ELIMINACIÓN DE DATOS? (escriba 'CONFIRMAR' para proceder): \";
\$handle = fopen(\"php://stdin\", \"r\");
\$response = trim(fgets(\$handle));
fclose(\$handle);

if (\$response !== 'CONFIRMAR') {
    echo \"❌ Operación cancelada. Los datos se mantienen seguros.\\n\";
    exit(0);
}

echo \"\\n🚀 Eliminando tablas con datos...\\n\\n\";

\$deletedTables = 0;
\$deletedRecords = 0;

foreach (\$tablesToClean as \$table) {
    try {
        if (Schema::hasTable(\$table)) {
            \$count = DB::table(\$table)->count();
            Schema::drop(\$table);
            echo \"✅ Eliminada: {\$table} ({\$count} registros)\\n\";
            \$deletedTables++;
            \$deletedRecords += \$count;
        }
    } catch (Exception \$e) {
        echo \"❌ Error eliminando {\$table}: \" . \$e->getMessage() . \"\\n\";
    }
}

echo \"\\n\" . str_repeat(\"=\", 50) . \"\\n\";
echo \"✅ LIMPIEZA DE DATOS COMPLETADA\\n\";
echo \"Tablas eliminadas: {\$deletedTables}\\n\";
echo \"Registros eliminados: {\$deletedRecords}\\n\";
echo \"\\n🎉 Base de datos optimizada.\\n\";
";

    $scriptPath = __DIR__ . '/cleanup_unused_data_tables.php';
    file_put_contents($scriptPath, $scriptContent);
    echo "\n✅ Script generado: {$scriptPath}\n";
    echo "Para ejecutar: php tools/cleanup_unused_data_tables.php\n";
}

echo "\n🎉 Análisis completado.\n";
