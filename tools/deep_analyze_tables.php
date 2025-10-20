<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

class DeepDatabaseAnalyzer
{
    private $tablesToAnalyze = ['orders', 'pending_recordings', 'user_permissions'];

    public function analyzeTableUsage()
    {
        echo "=== ANÁLISIS PROFUNDO DE TABLAS CANDIDATAS A ELIMINACIÓN ===\n\n";

        foreach ($this->tablesToAnalyze as $table) {
            echo "🔍 Analizando tabla: {$table}\n";
            echo str_repeat("-", 60) . "\n";

            $this->analyzeTable($table);
            echo "\n";
        }
    }

    private function analyzeTable($tableName)
    {
        try {
            // 1. Verificar si la tabla existe
            if (!$this->tableExists($tableName)) {
                echo "❌ La tabla '{$tableName}' no existe en la base de datos\n";
                return;
            }

            // 2. Obtener estructura de la tabla
            $structure = $this->getTableStructure($tableName);
            echo "📋 Estructura de la tabla:\n";
            foreach ($structure as $column) {
                echo "   - {$column->Field} ({$column->Type})\n";
            }

            // 3. Contar registros
            $count = DB::table($tableName)->count();
            echo "📊 Número de registros: {$count}\n";

            // 4. Buscar en archivos PHP
            echo "🔍 Buscando referencias en el código:\n";
            $this->searchInCode($tableName);

            // 5. Verificar relaciones FK
            echo "🔗 Verificando relaciones de clave foránea:\n";
            $this->checkForeignKeys($tableName);

            // 6. Verificar en rutas
            echo "🛣️ Verificando en rutas:\n";
            $this->searchInRoutes($tableName);

            // 7. Verificar en vistas
            echo "👁️ Verificando en vistas:\n";
            $this->searchInViews($tableName);

            // 8. Buscar en migraciones activas
            echo "📦 Verificando migraciones activas:\n";
            $this->checkActiveMigrations($tableName);

        } catch (\Exception $e) {
            echo "❌ Error analizando '{$tableName}': " . $e->getMessage() . "\n";
        }
    }

    private function tableExists($tableName)
    {
        try {
            DB::table($tableName)->limit(1)->get();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableStructure($tableName)
    {
        return DB::select("DESCRIBE {$tableName}");
    }

    private function searchInCode($tableName)
    {
        $searchPaths = [
            'app/Models/',
            'app/Http/Controllers/',
            'app/Services/',
            'app/Jobs/',
            'app/Mail/',
            'app/Notifications/'
        ];

        $found = false;
        foreach ($searchPaths as $path) {
            $fullPath = __DIR__ . '/../' . $path;
            if (is_dir($fullPath)) {
                $results = $this->searchInDirectory($fullPath, $tableName);
                if (!empty($results)) {
                    echo "   📁 En {$path}:\n";
                    foreach ($results as $file => $lines) {
                        echo "     - {$file}: líneas " . implode(', ', $lines) . "\n";
                    }
                    $found = true;
                }
            }
        }

        if (!$found) {
            echo "   ✅ No se encontraron referencias en el código PHP\n";
        }
    }

    private function searchInDirectory($directory, $tableName)
    {
        $results = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $lines = explode("\n", $content);
                $foundLines = [];

                foreach ($lines as $lineNum => $line) {
                    if (stripos($line, $tableName) !== false) {
                        $foundLines[] = $lineNum + 1;
                    }
                }

                if (!empty($foundLines)) {
                    $relativePath = str_replace(__DIR__ . '/../', '', $file->getPathname());
                    $results[$relativePath] = $foundLines;
                }
            }
        }

        return $results;
    }

    private function checkForeignKeys($tableName)
    {
        try {
            // Claves foráneas que referencian esta tabla
            $incomingFks = DB::select("
                SELECT
                    TABLE_NAME,
                    COLUMN_NAME,
                    CONSTRAINT_NAME
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    REFERENCED_TABLE_SCHEMA = DATABASE() AND
                    REFERENCED_TABLE_NAME = ?
            ", [$tableName]);

            // Claves foráneas desde esta tabla
            $outgoingFks = DB::select("
                SELECT
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME,
                    CONSTRAINT_NAME
                FROM
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                    TABLE_SCHEMA = DATABASE() AND
                    TABLE_NAME = ? AND
                    REFERENCED_TABLE_NAME IS NOT NULL
            ", [$tableName]);

            if (empty($incomingFks) && empty($outgoingFks)) {
                echo "   ✅ No tiene relaciones de clave foránea\n";
            } else {
                if (!empty($incomingFks)) {
                    echo "   ⚠️ Otras tablas la referencian:\n";
                    foreach ($incomingFks as $fk) {
                        echo "     - {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} -> {$tableName}\n";
                    }
                }
                if (!empty($outgoingFks)) {
                    echo "   📍 Referencias a otras tablas:\n";
                    foreach ($outgoingFks as $fk) {
                        echo "     - {$tableName}.{$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
                    }
                }
            }
        } catch (\Exception $e) {
            echo "   ❌ Error verificando FKs: " . $e->getMessage() . "\n";
        }
    }

    private function searchInRoutes($tableName)
    {
        $routeFiles = [
            'routes/web.php',
            'routes/api.php',
            'routes/auth.php'
        ];

        $found = false;
        foreach ($routeFiles as $routeFile) {
            $fullPath = __DIR__ . '/../' . $routeFile;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                if (stripos($content, $tableName) !== false) {
                    echo "   ⚠️ Encontrado en {$routeFile}\n";
                    $found = true;
                }
            }
        }

        if (!$found) {
            echo "   ✅ No se encontró en archivos de rutas\n";
        }
    }

    private function searchInViews($tableName)
    {
        $viewsPath = __DIR__ . '/../resources/views/';
        if (is_dir($viewsPath)) {
            $results = $this->searchInDirectory($viewsPath, $tableName);
            if (!empty($results)) {
                echo "   ⚠️ Encontrado en vistas:\n";
                foreach ($results as $file => $lines) {
                    echo "     - {$file}\n";
                }
            } else {
                echo "   ✅ No se encontró en vistas\n";
            }
        }
    }

    private function checkActiveMigrations($tableName)
    {
        try {
            // Verificar si hay migraciones ejecutadas para esta tabla
            $migrations = DB::table('migrations')
                ->where('migration', 'like', "%{$tableName}%")
                ->get();

            if ($migrations->count() > 0) {
                echo "   📦 Migraciones ejecutadas:\n";
                foreach ($migrations as $migration) {
                    echo "     - {$migration->migration}\n";
                }
            } else {
                echo "   ✅ No tiene migraciones ejecutadas registradas\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Error verificando migraciones: " . $e->getMessage() . "\n";
        }
    }

    public function generateSafetyReport()
    {
        echo "\n=== REPORTE DE SEGURIDAD PARA ELIMINACIÓN ===\n\n";

        foreach ($this->tablesToAnalyze as $table) {
            $isSafe = $this->isTableSafeToDelete($table);
            $icon = $isSafe ? "✅" : "❌";
            $status = $isSafe ? "SEGURO ELIMINAR" : "NO ELIMINAR";

            echo "{$icon} {$table}: {$status}\n";

            if (!$isSafe) {
                $reasons = $this->getBlockingReasons($table);
                foreach ($reasons as $reason) {
                    echo "   - {$reason}\n";
                }
            }
        }

        echo "\n=== RECOMENDACIÓN FINAL ===\n";
        $safeTables = array_filter($this->tablesToAnalyze, [$this, 'isTableSafeToDelete']);

        if (empty($safeTables)) {
            echo "⚠️ No se recomienda eliminar ninguna tabla en este momento.\n";
        } else {
            echo "✅ Se pueden eliminar de forma segura: " . implode(', ', $safeTables) . "\n";
        }
    }

    private function isTableSafeToDelete($tableName)
    {
        // Verificaciones de seguridad
        try {
            // 1. No debe tener datos
            $hasData = DB::table($tableName)->count() > 0;
            if ($hasData) return false;

            // 2. No debe tener FKs entrantes
            $incomingFks = DB::select("
                SELECT COUNT(*) as count
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?
            ", [$tableName]);

            if ($incomingFks[0]->count > 0) return false;

            // 3. No debe estar en código crítico
            $codeReferences = $this->searchInDirectory(__DIR__ . '/../app/', $tableName);
            if (!empty($codeReferences)) return false;

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getBlockingReasons($tableName)
    {
        $reasons = [];

        try {
            // Verificar datos
            $count = DB::table($tableName)->count();
            if ($count > 0) {
                $reasons[] = "Contiene {$count} registros";
            }

            // Verificar FKs entrantes
            $incomingFks = DB::select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?
            ", [$tableName]);

            if (!empty($incomingFks)) {
                $tables = array_map(function($fk) { return $fk->TABLE_NAME; }, $incomingFks);
                $reasons[] = "Referenciada por tablas: " . implode(', ', $tables);
            }

            // Verificar código
            $codeReferences = $this->searchInDirectory(__DIR__ . '/../app/', $tableName);
            if (!empty($codeReferences)) {
                $reasons[] = "Usada en código: " . implode(', ', array_keys($codeReferences));
            }

        } catch (\Exception $e) {
            $reasons[] = "Error en verificación: " . $e->getMessage();
        }

        return $reasons;
    }
}

// Ejecutar análisis profundo
try {
    $analyzer = new DeepDatabaseAnalyzer();
    $analyzer->analyzeTableUsage();
    $analyzer->generateSafetyReport();
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
