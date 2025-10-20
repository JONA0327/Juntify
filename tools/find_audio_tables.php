<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== BUSCANDO TABLAS RELACIONADAS CON AUDIOS PENDIENTES ===\n\n";

try {
    // Buscar tablas que contengan 'audio', 'pending', 'recording' en el nombre
    $tables = DB::select('SHOW TABLES');
    echo "Tablas que podrían estar relacionadas con audios:\n";
    echo str_repeat("-", 60) . "\n";

    foreach($tables as $table) {
        $tableArray = (array) $table;
        $tableName = array_values($tableArray)[0];

        if(stripos($tableName, 'audio') !== false ||
           stripos($tableName, 'pending') !== false ||
           stripos($tableName, 'recording') !== false ||
           stripos($tableName, 'transcription') !== false ||
           stripos($tableName, 'meeting') !== false) {

            echo "\n📋 Tabla: {$tableName}\n";

            // Mostrar estructura y datos
            try {
                $count = DB::table($tableName)->count();
                echo "   Registros: {$count}\n";

                // Obtener estructura completa
                $columns = DB::select("DESCRIBE {$tableName}");
                echo "   Campos:\n";

                $relevantColumns = [];
                foreach($columns as $col) {
                    $isRelevant = stripos($col->Field, 'audio') !== false ||
                                 stripos($col->Field, 'file') !== false ||
                                 stripos($col->Field, 'path') !== false ||
                                 stripos($col->Field, 'url') !== false ||
                                 stripos($col->Field, 'drive') !== false ||
                                 stripos($col->Field, 'recording') !== false;

                    $marker = $isRelevant ? "🔍" : "   ";
                    echo "   {$marker} {$col->Field} ({$col->Type})\n";

                    if($isRelevant) {
                        $relevantColumns[] = $col->Field;
                    }
                }

                // Si tiene datos, mostrar algunos ejemplos de los campos relevantes
                if($count > 0 && !empty($relevantColumns)) {
                    echo "\n   📊 Datos de ejemplo (campos relevantes):\n";
                    $sampleData = DB::table($tableName)->limit(3)->get($relevantColumns);
                    foreach($sampleData as $index => $row) {
                        echo "     Registro " . ($index + 1) . ":\n";
                        foreach($relevantColumns as $col) {
                            $value = isset($row->$col) ? $row->$col : 'NULL';
                            if(strlen($value) > 50) {
                                $value = substr($value, 0, 47) . '...';
                            }
                            echo "       {$col}: {$value}\n";
                        }
                    }
                }

            } catch(Exception $e) {
                echo "   ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "ANÁLISIS ESPECÍFICO DE PENDING_RECORDINGS\n";
    echo str_repeat("=", 60) . "\n";

    // Análisis específico de pending_recordings
    if(DB::getSchemaBuilder()->hasTable('pending_recordings')) {
        echo "\n📋 Tabla pending_recordings encontrada\n";

        $structure = DB::select("DESCRIBE pending_recordings");
        echo "\nEstructura completa:\n";
        foreach($structure as $col) {
            echo "  • {$col->Field}: {$col->Type}" .
                 ($col->Null === 'YES' ? ' (nullable)' : ' (required)') .
                 ($col->Default ? " default: {$col->Default}" : '') . "\n";
        }

        $count = DB::table('pending_recordings')->count();
        echo "\nRegistros actuales: {$count}\n";

        // Verificar si existen registros y mostrarlos
        if($count > 0) {
            echo "\nDatos existentes:\n";
            $records = DB::table('pending_recordings')->get();
            foreach($records as $i => $record) {
                echo "\nRegistro " . ($i + 1) . ":\n";
                echo "  - Username: {$record->username}\n";
                echo "  - Meeting: {$record->meeting_name}\n";
                echo "  - Audio Drive ID: {$record->audio_drive_id}\n";
                echo "  - Status: {$record->status}\n";
                echo "  - Created: {$record->created_at}\n";
            }
        }

        // Buscar en el código si se usa
        echo "\n🔍 Buscando uso en el código...\n";
        $searchPaths = [
            'app/Models/',
            'app/Http/Controllers/',
            'app/Jobs/',
            'app/Services/'
        ];

        $found = false;
        foreach($searchPaths as $path) {
            $fullPath = __DIR__ . '/../' . $path;
            if(is_dir($fullPath)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath));
                foreach($iterator as $file) {
                    if($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());
                        if(stripos($content, 'pending_recordings') !== false ||
                           stripos($content, 'PendingRecording') !== false) {
                            $relativePath = str_replace(__DIR__ . '/../', '', $file->getPathname());
                            echo "  ✅ Encontrado en: {$relativePath}\n";
                            $found = true;
                        }
                    }
                }
            }
        }

        if(!$found) {
            echo "  ❌ No se encontró uso en el código\n";
        }

    } else {
        echo "\n❌ La tabla pending_recordings no existe\n";
    }

} catch(Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
}

echo "\n=== RECOMENDACIÓN ===\n";
echo "La tabla 'pending_recordings' parece ser para almacenar grabaciones de audio\n";
echo "que están pendientes de procesamiento. Si no se usa actualmente y está vacía,\n";
echo "se puede eliminar de forma segura.\n";
