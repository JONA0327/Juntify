<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== REVISIÓN COMPLETA DE TABLAS INNECESARIAS ===\n\n";

// TABLAS PROTEGIDAS (NO SE PUEDEN BORRAR)
$protectedTables = [
    // Tablas principales del sistema
    'users',
    'google_tokens',
    'folders',
    'subfolders',
    'transcriptions_laravel',
    'meeting_content_containers',
    'pending_recordings', // Confirmado que se usa activamente
    'user_permissions',   // Solicitado mantener por el usuario

    // Tablas de organizaciones/empresas/planes
    'organizations',
    'organization_members',
    'organization_invitations',
    'plans',
    'subscriptions',
    'payments',
    'mercado_pago_payments',

    // Tablas del sistema de reuniones
    'meetings',
    'meeting_participants',
    'shared_meetings',
    'meeting_recordings',

    // Tablas de autenticación y sesiones
    'password_resets',
    'sessions',
    'personal_access_tokens',

    // Tablas del sistema de archivos y grupos
    'groups',
    'group_members',
    'group_invitations',
    'tasks_laravel',
    'notifications',

    // Tablas de migración de Laravel
    'migrations',
    'failed_jobs',
    'jobs',

    // Tablas específicas comparando con DDU
    'permissions',              // Existe en DDU - podría ser necesaria
    'user_panel_miembros',      // Existe en DDU - podría ser necesaria
    'meeting_content_relations' // Existe en DDU - podría ser necesaria
];

// Obtener todas las tablas de la base de datos
$allTables = DB::select('SHOW TABLES');
$tableColumn = 'Tables_in_' . env('DB_DATABASE');
$currentTables = array_map(function($table) use ($tableColumn) {
    return $table->$tableColumn;
}, $allTables);

echo "📊 TOTAL DE TABLAS EN LA BASE DE DATOS: " . count($currentTables) . "\n\n";

// Separar tablas protegidas de las candidatas a eliminación
$candidatesForDeletion = [];
$existingProtected = [];
$notFoundProtected = [];

foreach ($currentTables as $table) {
    if (in_array($table, $protectedTables)) {
        $existingProtected[] = $table;
    } else {
        $candidatesForDeletion[] = $table;
    }
}

// Verificar tablas protegidas que no existen
foreach ($protectedTables as $protected) {
    if (!in_array($protected, $currentTables)) {
        $notFoundProtected[] = $protected;
    }
}

echo "✅ TABLAS PROTEGIDAS (EXISTENTES): " . count($existingProtected) . "\n";
echo str_repeat("-", 50) . "\n";
foreach ($existingProtected as $table) {
    try {
        $count = DB::table($table)->count();
        echo "  • {$table} (Registros: {$count})\n";
    } catch (Exception $e) {
        echo "  • {$table} (Error contando registros)\n";
    }
}

if (!empty($notFoundProtected)) {
    echo "\n⚠️  TABLAS PROTEGIDAS (NO ENCONTRADAS): " . count($notFoundProtected) . "\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($notFoundProtected as $table) {
        echo "  • {$table}\n";
    }
}

echo "\n🔍 CANDIDATAS PARA ELIMINACIÓN: " . count($candidatesForDeletion) . "\n";
echo str_repeat("=", 60) . "\n";

$realCandidates = [];

foreach ($candidatesForDeletion as $table) {
    echo "\n📋 ANALIZANDO: {$table}\n";
    echo str_repeat("-", 30) . "\n";

    try {
        // Contar registros
        $count = DB::table($table)->count();
        echo "  Registros: {$count}\n";

        // Verificar estructura de la tabla
        $columns = Schema::getColumnListing($table);
        echo "  Columnas: " . implode(', ', $columns) . "\n";

        // Buscar referencias en el código
        $searchPattern = escapeshellarg($table);
        $command = "cd " . escapeshellarg(__DIR__ . '/..') . " && findstr /R /I /S \"$table\" app\\*.php resources\\*.php routes\\*.php config\\*.php 2>nul";

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        $codeReferences = count($output);
        echo "  Referencias en código: {$codeReferences}\n";

        if ($codeReferences > 0) {
            echo "  Archivos con referencias:\n";
            foreach (array_slice($output, 0, 3) as $reference) {
                echo "    - " . trim($reference) . "\n";
            }
            if (count($output) > 3) {
                echo "    ... y " . (count($output) - 3) . " más\n";
            }
        }

        // Determinar si es candidata real
        if ($count == 0 && $codeReferences == 0) {
            $realCandidates[] = $table;
            echo "  🗑️  CANDIDATA REAL PARA ELIMINACIÓN\n";
        } else {
            echo "  ✅ MANTENER (tiene datos o referencias en código)\n";
        }

    } catch (Exception $e) {
        echo "  ❌ Error analizando: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RESUMEN FINAL\n";
echo str_repeat("=", 60) . "\n";
echo "Tablas protegidas (conservar): " . count($existingProtected) . "\n";
echo "Candidatas analizadas: " . count($candidatesForDeletion) . "\n";
echo "Tablas REALMENTE innecesarias: " . count($realCandidates) . "\n\n";

if (!empty($realCandidates)) {
    echo "🗑️  TABLAS SEGURAS PARA ELIMINAR:\n";
    echo str_repeat("-", 40) . "\n";
    foreach ($realCandidates as $table) {
        echo "  • {$table}\n";
    }

    echo "\n¿Generar script de limpieza para estas tablas? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
        generateCleanupScript($realCandidates);
    }
} else {
    echo "✅ No se encontraron tablas innecesarias adicionales para eliminar.\n";
    echo "La base de datos está optimizada correctamente.\n";
}

function generateCleanupScript($tablesToDelete) {
    $scriptContent = "<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
\$app = require_once __DIR__ . '/../bootstrap/app.php';
\$kernel = \$app->make(Illuminate\\Contracts\\Console\\Kernel::class);
\$kernel->bootstrap();

echo \"=== LIMPIEZA FINAL DE TABLAS INNECESARIAS ===\\n\\n\";

\$tablesToDelete = [
";

    foreach ($tablesToDelete as $table) {
        $scriptContent .= "    '{$table}',\n";
    }

    $scriptContent .= "];

echo \"🗑️  TABLAS A ELIMINAR:\\n\";
foreach (\$tablesToDelete as \$table) {
    if (Schema::hasTable(\$table)) {
        \$count = DB::table(\$table)->count();
        echo \"  • {\$table} (Registros: {\$count})\\n\";
    }
}

echo \"\\n¿Continuar con la eliminación? (y/n): \";
\$handle = fopen(\"php://stdin\", \"r\");
\$response = trim(fgets(\$handle));
fclose(\$handle);

if (strtolower(\$response) !== 'y') {
    echo \"❌ Operación cancelada.\\n\";
    exit(0);
}

echo \"\\n🚀 Eliminando tablas...\\n\\n\";

\$deleted = 0;
foreach (\$tablesToDelete as \$table) {
    try {
        if (Schema::hasTable(\$table)) {
            Schema::drop(\$table);
            echo \"✅ Eliminada: {\$table}\\n\";
            \$deleted++;
        }
    } catch (Exception \$e) {
        echo \"❌ Error eliminando {\$table}: \" . \$e->getMessage() . \"\\n\";
    }
}

echo \"\\n✅ Limpieza completada. Tablas eliminadas: {\$deleted}\\n\";
";

    $scriptPath = __DIR__ . '/final_cleanup_unnecessary_tables.php';
    file_put_contents($scriptPath, $scriptContent);
    echo "\n✅ Script generado: {$scriptPath}\n";
    echo "Para ejecutar: php tools/final_cleanup_unnecessary_tables.php\n";
}

echo "\n🎉 Análisis completo finalizado.\n";
