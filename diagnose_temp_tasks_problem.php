<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TranscriptionTemp;
use App\Models\TaskLaravel;
use App\Models\PendingRecording;
use Illuminate\Support\Facades\Storage;

echo "=== Diagnóstico del Problema de Tareas en Reuniones Temporales ===\n\n";

// 1. Verificar la reunión temporal "Kualifin Nuevo Cliente"
echo "1. Verificando reunión temporal ID 11:\n";
$meeting = TranscriptionTemp::find(11);

if ($meeting) {
    echo "   ✓ Reunión encontrada: {$meeting->title}\n";
    echo "   - Usuario: {$meeting->user_id}\n";
    echo "   - Creada: {$meeting->created_at}\n";
    echo "   - Audio path: {$meeting->audio_path}\n";
    echo "   - Transcription path: {$meeting->transcription_path}\n";

    // Verificar si existe el archivo de transcripción
    if ($meeting->transcription_path && Storage::disk('local')->exists($meeting->transcription_path)) {
        echo "   ✓ Archivo de transcripción existe\n";

        // Intentar leer el contenido del archivo
        try {
            $content = Storage::disk('local')->get($meeting->transcription_path);

            // Verificar si está encriptado (archivos .ju suelen estarlo)
            $isEncrypted = false;
            if (strlen($content) > 0 && !str_starts_with(trim($content), '{')) {
                $isEncrypted = true;
                echo "   ⚠️  Archivo parece estar encriptado (.ju format)\n";

                // Intentar desencriptar usando el trait
                if (method_exists(App\Http\Controllers\SharedMeetingController::class, 'decryptJuFile')) {
                    try {
                        $controller = new App\Http\Controllers\SharedMeetingController();
                        $result = $controller->decryptJuFile($content);
                        $decoded = $result['data'] ?? null;

                        if ($decoded) {
                            echo "   ✓ Archivo desencriptado exitosamente\n";
                        }
                    } catch (\Exception $e) {
                        echo "   ✗ Error desencriptando: " . $e->getMessage() . "\n";
                        $decoded = null;
                    }
                } else {
                    $decoded = null;
                }
            } else {
                $decoded = json_decode($content, true);
            }

            if ($decoded) {
                echo "   ✓ Contenido de transcripción válido\n";

                if (isset($decoded['tasks'])) {
                    echo "   - Tareas en JSON: " . count($decoded['tasks']) . "\n";
                    if (count($decoded['tasks']) > 0) {
                        echo "     Primera tarea: " . (is_array($decoded['tasks'][0]) ? json_encode($decoded['tasks'][0]) : $decoded['tasks'][0]) . "\n";
                    }
                } else {
                    echo "   ✗ No hay campo 'tasks' en el JSON\n";
                }

                if (isset($decoded['summary'])) {
                    echo "   - Resumen disponible: " . (strlen($decoded['summary']) > 100 ? substr($decoded['summary'], 0, 100) . '...' : $decoded['summary']) . "\n";
                }

                if (isset($decoded['key_points'])) {
                    echo "   - Puntos clave: " . count($decoded['key_points']) . "\n";
                }
            } else {
                echo "   ✗ Contenido de transcripción inválido\n";
            }
        } catch (\Exception $e) {
            echo "   ✗ Error leyendo transcripción: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ✗ Archivo de transcripción no existe\n";
    }

    // Verificar tareas en la base de datos
    $dbTasks = TaskLaravel::where('meeting_id', $meeting->id)
        ->where('meeting_type', 'temporary')
        ->get();

    echo "   - Tareas en BD: {$dbTasks->count()}\n";

    // Verificar tareas en el campo JSON del modelo
    $jsonTasks = $meeting->tasks ?? [];
    echo "   - Tareas en JSON del modelo: " . count($jsonTasks) . "\n";

} else {
    echo "   ✗ Reunión no encontrada\n";
}

// 2. Verificar pending recordings
echo "\n2. Verificando PendingRecording relacionados:\n";
$pendingRecordings = PendingRecording::where('meeting_name', 'LIKE', '%Kualifin%')->get();

if ($pendingRecordings->count() > 0) {
    foreach ($pendingRecordings as $pending) {
        echo "   - ID: {$pending->id}, Status: {$pending->status}\n";
        echo "     Metadata: " . json_encode($pending->metadata) . "\n";
    }
} else {
    echo "   ✗ No se encontraron PendingRecording relacionados\n";
}

// 3. Verificar el proceso de generación de tareas
echo "\n3. Verificando proceso de análisis:\n";

$transcriptionPath = $meeting ? $meeting->transcription_path : null;
if ($transcriptionPath && Storage::disk('local')->exists($transcriptionPath)) {
    echo "   ✓ Archivo de transcripción disponible para análisis\n";

    // Simular el proceso de extracción de tareas
    try {
        $content = Storage::disk('local')->get($transcriptionPath);
        $data = json_decode($content, true);

        if ($data && isset($data['tasks']) && is_array($data['tasks']) && count($data['tasks']) > 0) {
            echo "   ✓ Tareas encontradas en el archivo JSON\n";
            echo "   - Cantidad de tareas: " . count($data['tasks']) . "\n";

            // Verificar si las tareas deberían estar en la BD
            foreach ($data['tasks'] as $index => $task) {
                echo "     Tarea " . ($index + 1) . ": ";
                if (is_string($task)) {
                    echo $task . "\n";
                } elseif (is_array($task)) {
                    $taskText = $task['tarea'] ?? $task['text'] ?? $task['title'] ?? 'Sin texto';
                    echo $taskText . "\n";
                }
            }

            // El problema está aquí: las tareas están en el JSON pero no se transfieren a la BD
            echo "\n   🔍 DIAGNÓSTICO: Las tareas existen en el archivo JSON pero no se han guardado en tasks_laravel\n";
            echo "   📝 SOLUCIÓN REQUERIDA: Implementar proceso automático que transfiera tareas del JSON a la BD\n";

        } else {
            echo "   ✗ No hay tareas válidas en el archivo JSON\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ Error procesando archivo: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ No hay archivo de transcripción para analizar\n";
}

echo "\n=== RESUMEN DEL PROBLEMA ===\n";
echo "❌ Las reuniones temporales no ejecutan el proceso de análisis que genera tareas automáticamente\n";
echo "❌ El archivo JSON contiene tareas pero no se transfieren a la tabla tasks_laravel\n";
echo "❌ El frontend muestra 'No se identificaron tareas' porque busca en la BD, no en el JSON\n";

echo "\n=== SOLUCIONES PROPUESTAS ===\n";
echo "1. 🔧 Crear un job/proceso que analice reuniones temporales completadas\n";
echo "2. 🔧 Implementar transferencia automática de tareas del JSON a la BD\n";
echo "3. 🔧 Modificar el frontend para mostrar tareas del JSON como fallback\n";

echo "\nDiagnóstico completado.\n";
