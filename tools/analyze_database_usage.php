<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

class DatabaseAnalyzer
{
    private $excludePatterns = [
        'migrations',
        'password_resets',
        'password_reset_tokens',
        'personal_access_tokens',
        'failed_jobs',
        'jobs',
        'job_batches'
    ];

    private $coreSystemTables = [
        'users',
        'organizations',
        'organization_users',
        'plans',
        'subscriptions',
        'payments',
        'mercado_pago_payments',
        'ai_daily_usage'
    ];

    public function analyzeDatabaseUsage()
    {
        echo "=== ANÁLISIS DE USO DE BASE DE DATOS ===\n\n";

        // Obtener todas las tablas de la base de datos
        $tables = $this->getAllTables();
        echo "Total de tablas encontradas: " . count($tables) . "\n\n";

        // Analizar uso de cada tabla
        $analysis = [];
        foreach ($tables as $table) {
            $analysis[$table] = $this->analyzeTableUsage($table);
        }

        // Mostrar resultados
        $this->displayResults($analysis);

        return $analysis;
    }

    private function getAllTables()
    {
        $tables = [];
        $results = DB::select('SHOW TABLES');

        foreach ($results as $result) {
            $tableArray = (array) $result;
            $tables[] = array_values($tableArray)[0];
        }

        return $tables;
    }

    private function analyzeTableUsage($tableName)
    {
        try {
            // Verificar si la tabla existe en migraciones
            $migrationFiles = $this->findMigrationFiles($tableName);

            // Verificar si la tabla tiene datos
            $rowCount = DB::table($tableName)->count();

            // Verificar si la tabla está siendo referenciada en modelos
            $modelReferences = $this->findModelReferences($tableName);

            // Verificar si la tabla está siendo usada en controladores
            $controllerUsage = $this->findControllerUsage($tableName);

            // Verificar si es una tabla del sistema core
            $isCoreTable = $this->isCoreSystemTable($tableName);

            // Verificar si tiene relaciones con otras tablas
            $hasRelations = $this->hasTableRelations($tableName);

            return [
                'name' => $tableName,
                'row_count' => $rowCount,
                'migration_files' => $migrationFiles,
                'model_references' => $modelReferences,
                'controller_usage' => $controllerUsage,
                'is_core_table' => $isCoreTable,
                'has_relations' => $hasRelations,
                'can_delete' => $this->canDeleteTable($tableName, $rowCount, $modelReferences, $controllerUsage, $isCoreTable, $hasRelations)
            ];
        } catch (\Exception $e) {
            return [
                'name' => $tableName,
                'error' => $e->getMessage(),
                'can_delete' => false
            ];
        }
    }

    private function findMigrationFiles($tableName)
    {
        $migrationPath = __DIR__ . '/../database/migrations/';
        $files = [];

        if (is_dir($migrationPath)) {
            $iterator = new DirectoryIterator($migrationPath);
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (strpos($content, $tableName) !== false) {
                        $files[] = $file->getFilename();
                    }
                }
            }
        }

        return $files;
    }

    private function findModelReferences($tableName)
    {
        $modelPath = __DIR__ . '/../app/Models/';
        $references = [];

        if (is_dir($modelPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelPath));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (strpos($content, $tableName) !== false ||
                        strpos($content, "'" . $tableName . "'") !== false ||
                        strpos($content, '"' . $tableName . '"') !== false) {
                        $references[] = $file->getFilename();
                    }
                }
            }
        }

        return $references;
    }

    private function findControllerUsage($tableName)
    {
        $controllerPath = __DIR__ . '/../app/Http/Controllers/';
        $usage = [];

        if (is_dir($controllerPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllerPath));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (strpos($content, $tableName) !== false) {
                        $usage[] = $file->getFilename();
                    }
                }
            }
        }

        return $usage;
    }

    private function isCoreSystemTable($tableName)
    {
        return in_array($tableName, $this->coreSystemTables) ||
               in_array($tableName, $this->excludePatterns);
    }

    private function hasTableRelations($tableName)
    {
        try {
            // Verificar foreign keys que referencian esta tabla
            $foreignKeys = DB::select("
                SELECT
                    TABLE_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    REFERENCED_TABLE_SCHEMA = DATABASE() AND
                    REFERENCED_TABLE_NAME = ?
            ", [$tableName]);

            // Verificar foreign keys desde esta tabla
            $outgoingKeys = DB::select("
                SELECT
                    TABLE_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    TABLE_SCHEMA = DATABASE() AND
                    TABLE_NAME = ? AND
                    REFERENCED_TABLE_NAME IS NOT NULL
            ", [$tableName]);

            return count($foreignKeys) > 0 || count($outgoingKeys) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function canDeleteTable($tableName, $rowCount, $modelReferences, $controllerUsage, $isCoreTable, $hasRelations)
    {
        // No eliminar tablas del sistema core
        if ($isCoreTable) {
            return false;
        }

        // No eliminar si tiene relaciones con otras tablas
        if ($hasRelations) {
            return false;
        }

        // No eliminar si está siendo usada en modelos o controladores
        if (!empty($modelReferences) || !empty($controllerUsage)) {
            return false;
        }

        // No eliminar si tiene datos (más de 0 filas)
        if ($rowCount > 0) {
            return false;
        }

        return true;
    }

    private function displayResults($analysis)
    {
        echo "=== RESULTADOS DEL ANÁLISIS ===\n\n";

        $canDelete = [];
        $cannotDelete = [];

        foreach ($analysis as $table => $data) {
            if (isset($data['error'])) {
                echo "❌ Error analizando tabla '{$table}': {$data['error']}\n";
                continue;
            }

            if ($data['can_delete']) {
                $canDelete[] = $data;
            } else {
                $cannotDelete[] = $data;
            }
        }

        echo "🟢 TABLAS QUE SE PUEDEN ELIMINAR SEGURAMENTE (" . count($canDelete) . "):\n";
        echo str_repeat("-", 60) . "\n";
        foreach ($canDelete as $table) {
            echo "  • {$table['name']} (Filas: {$table['row_count']})\n";
            if (!empty($table['migration_files'])) {
                echo "    Migraciones: " . implode(', ', $table['migration_files']) . "\n";
            }
        }

        echo "\n🔴 TABLAS QUE NO SE DEBEN ELIMINAR (" . count($cannotDelete) . "):\n";
        echo str_repeat("-", 60) . "\n";
        foreach ($cannotDelete as $table) {
            echo "  • {$table['name']} (Filas: {$table['row_count']})\n";

            $reasons = [];
            if ($table['is_core_table']) $reasons[] = "Tabla del sistema";
            if ($table['has_relations']) $reasons[] = "Tiene relaciones";
            if (!empty($table['model_references'])) $reasons[] = "Usada en modelos";
            if (!empty($table['controller_usage'])) $reasons[] = "Usada en controladores";
            if ($table['row_count'] > 0) $reasons[] = "Contiene datos";

            if (!empty($reasons)) {
                echo "    Razones: " . implode(', ', $reasons) . "\n";
            }
        }

        echo "\n=== RESUMEN ===\n";
        echo "Total de tablas analizadas: " . count($analysis) . "\n";
        echo "Tablas que se pueden eliminar: " . count($canDelete) . "\n";
        echo "Tablas que se deben conservar: " . count($cannotDelete) . "\n";
    }

    public function generateCleanupScript($analysis)
    {
        $cleanupScript = "<?php\n\n";
        $cleanupScript .= "// Script generado automáticamente para limpiar base de datos\n";
        $cleanupScript .= "// Fecha: " . date('Y-m-d H:i:s') . "\n\n";

        $cleanupScript .= "require_once __DIR__ . '/../vendor/autoload.php';\n\n";
        $cleanupScript .= "\$app = require_once __DIR__ . '/../bootstrap/app.php';\n";
        $cleanupScript .= "\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);\n";
        $cleanupScript .= "\$kernel->bootstrap();\n\n";

        $cleanupScript .= "use Illuminate\Support\Facades\DB;\n";
        $cleanupScript .= "use Illuminate\Support\Facades\Schema;\n\n";

        $tablesToDelete = [];
        $migrationsToDelete = [];

        foreach ($analysis as $table => $data) {
            if (isset($data['can_delete']) && $data['can_delete']) {
                $tablesToDelete[] = $data['name'];
                $migrationsToDelete = array_merge($migrationsToDelete, $data['migration_files']);
            }
        }

        if (!empty($tablesToDelete)) {
            $cleanupScript .= "echo \"Iniciando limpieza de base de datos...\\n\\n\";\n\n";

            // Eliminar tablas
            $cleanupScript .= "// Eliminar tablas no utilizadas\n";
            $cleanupScript .= "\$tablesToDelete = " . var_export($tablesToDelete, true) . ";\n\n";

            $cleanupScript .= "foreach (\$tablesToDelete as \$table) {\n";
            $cleanupScript .= "    try {\n";
            $cleanupScript .= "        if (Schema::hasTable(\$table)) {\n";
            $cleanupScript .= "            Schema::drop(\$table);\n";
            $cleanupScript .= "            echo \"✅ Tabla eliminada: {\$table}\\n\";\n";
            $cleanupScript .= "        }\n";
            $cleanupScript .= "    } catch (Exception \$e) {\n";
            $cleanupScript .= "        echo \"❌ Error eliminando tabla {\$table}: \" . \$e->getMessage() . \"\\n\";\n";
            $cleanupScript .= "    }\n";
            $cleanupScript .= "}\n\n";

            // Eliminar migraciones
            if (!empty($migrationsToDelete)) {
                $cleanupScript .= "// Eliminar archivos de migración\n";
                $cleanupScript .= "\$migrationsToDelete = " . var_export(array_unique($migrationsToDelete), true) . ";\n\n";

                $cleanupScript .= "foreach (\$migrationsToDelete as \$migration) {\n";
                $cleanupScript .= "    \$filePath = __DIR__ . '/../database/migrations/' . \$migration;\n";
                $cleanupScript .= "    if (file_exists(\$filePath)) {\n";
                $cleanupScript .= "        unlink(\$filePath);\n";
                $cleanupScript .= "        echo \"🗑️ Migración eliminada: {\$migration}\\n\";\n";
                $cleanupScript .= "    }\n";
                $cleanupScript .= "}\n\n";
            }

            $cleanupScript .= "echo \"\\n✅ Limpieza completada.\\n\";\n";
        } else {
            $cleanupScript .= "echo \"No se encontraron tablas para eliminar.\\n\";\n";
        }

        return $cleanupScript;
    }
}

// Ejecutar análisis
try {
    $analyzer = new DatabaseAnalyzer();
    $analysis = $analyzer->analyzeDatabaseUsage();

    // Generar script de limpieza
    $cleanupScript = $analyzer->generateCleanupScript($analysis);
    file_put_contents(__DIR__ . '/cleanup_database.php', $cleanupScript);
    echo "\n📄 Script de limpieza generado: tools/cleanup_database.php\n";
    echo "⚠️  Revisa el análisis antes de ejecutar el script de limpieza.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
