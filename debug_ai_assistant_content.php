<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIAGNÓSTICO: ASISTENTE AI NO TOMA INFORMACIÓN DE .JU NI TAREAS ===\n\n";

try {
    // Autenticar usuario
    $user = \App\Models\User::where('email', 'goku03278@gmail.com')->first();
    \Auth::login($user);

    echo "1. VERIFICANDO CONTENIDO DE REUNIÓN TEMPORAL ID 14:\n";
    echo "===================================================\n";

    $meeting = \App\Models\TranscriptionTemp::find(14);
    if (!$meeting) {
        echo "❌ No se encontró la reunión temporal ID 14\n";
        exit(1);
    }

    echo "✅ Reunión encontrada:\n";
    echo "   - ID: {$meeting->id}\n";
    echo "   - Título: '{$meeting->title}'\n";
    echo "   - User ID: {$meeting->user_id}\n";
    echo "   - Archivo: {$meeting->file_path}\n";
    echo "   - Creada: {$meeting->created_at}\n";
    echo "   - Expira: {$meeting->expires_at}\n\n";

    // 2. Verificar si existe el archivo .ju
    echo "2. VERIFICANDO ARCHIVO .JU:\n";
    echo "============================\n";

    $filePath = storage_path('app/' . $meeting->file_path);
    echo "   - Ruta completa: {$filePath}\n";

    if (file_exists($filePath)) {
        echo "✅ Archivo .ju existe\n";
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if ($data) {
            echo "✅ JSON válido\n";
            echo "   - Tiene transcripción: " . (isset($data['transcription']) ? 'SÍ' : 'NO') . "\n";
            echo "   - Tiene tareas: " . (isset($data['tasks']) ? 'SÍ (' . count($data['tasks']) . ')' : 'NO') . "\n";
            echo "   - Tiene resumen: " . (isset($data['summary']) ? 'SÍ' : 'NO') . "\n";

            if (isset($data['transcription'])) {
                $transcription = substr($data['transcription'], 0, 200);
                echo "   - Inicio transcripción: {$transcription}...\n";
            }

            if (isset($data['tasks']) && count($data['tasks']) > 0) {
                echo "   - Tareas encontradas:\n";
                foreach ($data['tasks'] as $i => $task) {
                    echo "     " . ($i + 1) . ". {$task['title']}\n";
                }
            }
            echo "\n";
        } else {
            echo "❌ JSON inválido\n\n";
        }
    } else {
        echo "❌ Archivo .ju NO existe\n\n";
    }

    // 3. Probar el método del asistente AI
    echo "3. PROBANDO MÉTODO getTempMeetingContent:\n";
    echo "==========================================\n";

    // Crear instancia del controlador con dependencias
    $googleDriveService = app(\App\Services\GoogleDriveService::class);
    $googleTokenRefreshService = app(\App\Services\GoogleTokenRefreshService::class);
    $aiController = new \App\Http\Controllers\AiAssistantController($googleDriveService, $googleTokenRefreshService);

    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($aiController);
    $method = $reflection->getMethod('getTempMeetingContent');
    $method->setAccessible(true);

    $content = $method->invoke($aiController, 14);

    if ($content) {
        echo "✅ Método getTempMeetingContent funciona\n";
        echo "   - Tipo de respuesta: " . gettype($content) . "\n";

        if (is_array($content)) {
            echo "   - Claves disponibles: " . implode(', ', array_keys($content)) . "\n";

            if (isset($content['transcription'])) {
                echo "   - Transcripción (primeros 200 chars): " . substr($content['transcription'], 0, 200) . "...\n";
            }

            if (isset($content['tasks'])) {
                echo "   - Número de tareas: " . count($content['tasks']) . "\n";
                foreach ($content['tasks'] as $i => $task) {
                    echo "     " . ($i + 1) . ". {$task['title']}\n";
                }
            }
        } else {
            echo "   - Contenido: " . substr($content, 0, 200) . "...\n";
        }
        echo "\n";
    } else {
        echo "❌ Método getTempMeetingContent retorna NULL o FALSE\n\n";
    }

    // 4. Verificar el método buildTemporaryMeetingMetadata
    echo "4. VERIFICANDO buildTemporaryMeetingMetadata:\n";
    echo "=============================================\n";

    $metadataMethod = $reflection->getMethod('buildTemporaryMeetingMetadata');
    $metadataMethod->setAccessible(true);

    $metadata = $metadataMethod->invoke($aiController, $meeting, $content);

    echo "✅ Metadata generada:\n";
    echo "   - Título: {$metadata['title']}\n";
    echo "   - Fuente: {$metadata['source']}\n";
    echo "   - Has transcription: " . ($metadata['has_transcription'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Has tasks: " . ($metadata['has_tasks'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Task count: {$metadata['task_count']}\n";
    echo "   - Content length: {$metadata['content_length']}\n\n";

    // 5. Probar el método completo getAllUserMeetings
    echo "5. VERIFICANDO getAllUserMeetings (completo):\n";
    echo "==============================================\n";

    $allMeetingsMethod = $reflection->getMethod('getAllUserMeetings');
    $allMeetingsMethod->setAccessible(true);

    $allMeetings = $allMeetingsMethod->invoke($aiController, $user);

    echo "✅ Total de reuniones: " . count($allMeetings) . "\n";

    foreach ($allMeetings as $meetingData) {
        if ($meetingData['source'] === 'transcriptions_temp' && $meetingData['id'] == 14) {
            echo "✅ Reunión temporal ID 14 encontrada en lista:\n";
            echo "   - Título: {$meetingData['title']}\n";
            echo "   - Meeting name: {$meetingData['meeting_name']}\n";
            echo "   - Has transcription: " . ($meetingData['has_transcription'] ? 'SÍ' : 'NO') . "\n";
            echo "   - Has tasks: " . ($meetingData['has_tasks'] ? 'SÍ' : 'NO') . "\n";
            echo "   - Task count: {$meetingData['task_count']}\n";
            break;
        }
    }

    echo "\n=== DIAGNÓSTICO COMPLETO ===\n";
    echo "Si el archivo .ju existe y tiene contenido, pero el asistente AI no lo ve,\n";
    echo "podría ser un problema en el método getTempMeetingContent.\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
