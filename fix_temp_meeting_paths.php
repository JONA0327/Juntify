<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CORRIGIENDO file_path EN REUNIONES TEMPORALES ===\n\n";

try {
    // 1. Buscar reuniones temporales sin file_path
    echo "1. BUSCANDO REUNIONES SIN file_path:\n";
    echo "=====================================\n";

    $meetingsWithoutPath = \App\Models\TranscriptionTemp::whereNull('file_path')
        ->orWhere('file_path', '')
        ->get();

    echo "✅ Encontradas " . count($meetingsWithoutPath) . " reuniones sin file_path\n\n";

    foreach ($meetingsWithoutPath as $meeting) {
        echo "🔍 Procesando reunión ID {$meeting->id}: '{$meeting->title}'\n";
        echo "   - User ID: {$meeting->user_id}\n";
        echo "   - Creada: {$meeting->created_at}\n";

        // Buscar archivos .ju que podrían corresponder a esta reunión
        $userDir = "temp_transcriptions/{$meeting->user_id}";
        $storagePath = storage_path("app/{$userDir}");

        if (is_dir($storagePath)) {
            $files = glob($storagePath . "/*.ju");
            echo "   - Archivos .ju encontrados: " . count($files) . "\n";

            // Buscar por título similar
            $titleSlug = \Illuminate\Support\Str::slug($meeting->title);
            $matchingFile = null;

            foreach ($files as $file) {
                $filename = basename($file, '.ju');

                // Extraer el nombre antes del timestamp
                $parts = explode('_', $filename);
                if (count($parts) >= 2) {
                    array_pop($parts); // Remover timestamp
                    $fileTitle = implode('-', $parts);

                    if (str_contains($fileTitle, 'kualifin') && str_contains($titleSlug, 'kualifin')) {
                        $matchingFile = $file;
                        break;
                    }
                }
            }

            if ($matchingFile) {
                $relativePath = "{$userDir}/" . basename($matchingFile);
                echo "   ✅ Archivo coincidente: {$relativePath}\n";

                // Verificar contenido del archivo
                $content = file_get_contents($matchingFile);
                $data = json_decode($content, true);

                if ($data) {
                    echo "   ✅ JSON válido\n";
                    echo "   - Tiene transcripción: " . (isset($data['transcription']) ? 'SÍ' : 'NO') . "\n";
                    echo "   - Tiene tareas: " . (isset($data['tasks']) ? 'SÍ (' . count($data['tasks']) . ')' : 'NO') . "\n";

                    // Actualizar la base de datos
                    $meeting->file_path = $relativePath;
                    $meeting->save();

                    echo "   ✅ file_path actualizado en BD\n";
                } else {
                    echo "   ❌ JSON inválido\n";
                }
            } else {
                echo "   ⚠️ No se encontró archivo coincidente\n";

                // Mostrar archivos disponibles para debug
                echo "   📂 Archivos disponibles:\n";
                foreach ($files as $file) {
                    echo "     - " . basename($file) . "\n";
                }
            }
        } else {
            echo "   ❌ Directorio no existe: {$storagePath}\n";
        }

        echo "\n";
    }

    // 2. Verificar reunión específica ID 14
    echo "2. VERIFICANDO REUNIÓN ID 14 ESPECÍFICAMENTE:\n";
    echo "==============================================\n";

    $meeting14 = \App\Models\TranscriptionTemp::find(14);
    if ($meeting14) {
        echo "✅ Reunión ID 14 encontrada\n";
        echo "   - Título: '{$meeting14->title}'\n";
        echo "   - file_path actual: '{$meeting14->file_path}'\n";

        if (empty($meeting14->file_path)) {
            // Buscar archivo manualmente
            $userDir = "temp_transcriptions/{$meeting14->user_id}";
            $files = glob(storage_path("app/{$userDir}") . "/*kualifin*.ju");

            if (!empty($files)) {
                // Tomar el más reciente
                $latestFile = end($files);
                $relativePath = "{$userDir}/" . basename($latestFile);

                echo "   🔧 Asignando archivo: {$relativePath}\n";

                $meeting14->file_path = $relativePath;
                $meeting14->save();

                echo "   ✅ file_path actualizado\n";
            }
        }

        // Probar acceso al contenido
        if (!empty($meeting14->file_path)) {
            $fullPath = storage_path('app/' . $meeting14->file_path);
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                $data = json_decode($content, true);

                echo "   ✅ Archivo accesible\n";
                echo "   - Tamaño: " . number_format(strlen($content)) . " bytes\n";
                if ($data) {
                    echo "   - Transcripción: " . (isset($data['transcription']) ? 'SÍ' : 'NO') . "\n";
                    echo "   - Tareas: " . (isset($data['tasks']) ? count($data['tasks']) : 0) . "\n";
                }
            } else {
                echo "   ❌ Archivo no accesible: {$fullPath}\n";
            }
        }
    }

    echo "\n=== CORRECCIÓN COMPLETADA ===\n";
    echo "Ahora el asistente AI debería poder acceder al contenido de las reuniones temporales.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
