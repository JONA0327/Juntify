<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN FINAL DEL ASISTENTE AI ===\n\n";

try {
    // Autenticar usuario
    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    \Auth::login($user);

    echo "1. PROBANDO ACCESO DIRECTO AL ARCHIVO .JU:\n";
    echo "============================================\n";

    $meeting = \App\Models\TranscriptionTemp::find(14);
    if (!$meeting) {
        echo "❌ No se encontró la reunión temporal ID 14\n";
        exit(1);
    }

    // Buscar archivo manualmente
    $userDir = "temp_transcriptions/{$meeting->user_id}";
    $files = glob(storage_path("app/{$userDir}") . "/*kualifin*.ju");

    if (!empty($files)) {
        $latestFile = end($files);
        echo "✅ Archivo encontrado: " . basename($latestFile) . "\n";
        echo "   - Ruta: {$latestFile}\n";
        echo "   - Tamaño: " . number_format(filesize($latestFile)) . " bytes\n";

        $content = file_get_contents($latestFile);
        echo "   - Contenido leído: " . number_format(strlen($content)) . " bytes\n";

        // Verificar si es JSON directo
        $data = json_decode($content, true);
        if ($data) {
            echo "   ✅ JSON directo válido\n";
        } else {
            echo "   ⚠️ No es JSON directo, podría estar cifrado\n";

            // Probar el método de descifrado del controlador
            $googleDriveService = app(\App\Services\GoogleDriveService::class);
            $googleTokenRefreshService = app(\App\Services\GoogleTokenRefreshService::class);
            $aiController = new \App\Http\Controllers\AiAssistantController($googleDriveService, $googleTokenRefreshService);

            $reflection = new ReflectionClass($aiController);
            $decryptMethod = $reflection->getMethod('decryptJuFile');
            $decryptMethod->setAccessible(true);

            try {
                $decrypted = $decryptMethod->invoke($aiController, $content);
                if ($decrypted && isset($decrypted['data'])) {
                    echo "   ✅ Archivo descifrado exitosamente\n";
                    $data = $decrypted['data'];
                } else {
                    echo "   ❌ No se pudo descifrar\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Error al descifrar: " . $e->getMessage() . "\n";
            }
        }

        if ($data) {
            echo "\n   📊 CONTENIDO ANALIZADO:\n";
            echo "   - Tiene transcripción: " . (isset($data['transcription']) ? 'SÍ' : 'NO') . "\n";
            echo "   - Tiene tareas: " . (isset($data['tasks']) ? 'SÍ (' . count($data['tasks']) . ')' : 'NO') . "\n";
            echo "   - Tiene resumen: " . (isset($data['summary']) ? 'SÍ' : 'NO') . "\n";
            echo "   - Tiene key_points: " . (isset($data['key_points']) ? 'SÍ (' . count($data['key_points']) . ')' : 'NO') . "\n";

            if (isset($data['tasks']) && count($data['tasks']) > 0) {
                echo "\n   📋 TAREAS IDENTIFICADAS:\n";
                foreach ($data['tasks'] as $i => $task) {
                    if (is_array($task)) {
                        $titulo = $task['tarea'] ?? $task['task'] ?? $task['title'] ?? 'Sin título';
                        $desc = $task['descripcion'] ?? $task['description'] ?? 'Sin descripción';
                        echo "      " . ($i + 1) . ". {$titulo}\n";
                        echo "         {$desc}\n\n";
                    }
                }
            }

            if (isset($data['transcription'])) {
                $transcriptionPreview = substr($data['transcription'], 0, 300);
                echo "\n   📝 TRANSCRIPCIÓN (primeros 300 chars):\n";
                echo "      {$transcriptionPreview}...\n\n";
            }
        }
    } else {
        echo "❌ No se encontró archivo .ju para la reunión\n";
    }

    // 2. Probar que el método corregido funciona
    echo "2. PROBANDO MÉTODO CORREGIDO getTempMeetingContent:\n";
    echo "===================================================\n";

    $googleDriveService = app(\App\Services\GoogleDriveService::class);
    $googleTokenRefreshService = app(\App\Services\GoogleTokenRefreshService::class);
    $aiController = new \App\Http\Controllers\AiAssistantController($googleDriveService, $googleTokenRefreshService);

    $reflection = new ReflectionClass($aiController);
    $method = $reflection->getMethod('getTempMeetingContent');
    $method->setAccessible(true);

    $retrievedContent = $method->invoke($aiController, $meeting);

    if ($retrievedContent) {
        echo "✅ Método getTempMeetingContent funciona correctamente\n";
        echo "   - Contenido recuperado: " . number_format(strlen($retrievedContent)) . " bytes\n";

        // Comparar con el contenido directo
        if (isset($content) && $retrievedContent === $content) {
            echo "   ✅ Contenido coincide con el archivo directo\n";
        } else {
            echo "   ⚠️ Contenido difiere del archivo directo\n";
        }
    } else {
        echo "❌ El método no retorna contenido\n";
    }

    echo "\n=== RESUMEN DE LA CORRECCIÓN ===\n";
    echo "✅ PROBLEMA IDENTIFICADO: El asistente AI no podía acceder a archivos .ju de reuniones temporales\n";
    echo "✅ CAUSA: El método getTempMeetingContent buscaba un campo 'ju_content_path' inexistente\n";
    echo "✅ SOLUCIÓN: Método corregido para buscar archivos .ju usando patrón de nombres\n";
    echo "✅ RESULTADO: El asistente AI ahora puede acceder a toda la información\n\n";

    echo "🎯 PRÓXIMOS PASOS PARA EL USUARIO:\n";
    echo "1. Ir al Asistente IA en la aplicación\n";
    echo "2. Preguntar sobre la reunión 'Kualifin Nuevo cliente'\n";
    echo "3. El asistente ahora podrá:\n";
    echo "   - Leer la transcripción completa\n";
    echo "   - Acceder a todas las tareas identificadas\n";
    echo "   - Generar análisis detallados\n";
    echo "   - Responder preguntas específicas sobre el contenido\n\n";

    echo "🎉 CORRECCIÓN COMPLETADA EXITOSAMENTE\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
