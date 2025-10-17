<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TranscriptionTemp;
use App\Traits\MeetingContentParsing;
use Illuminate\Support\Facades\Storage;

echo "=== Debug del Contenido de la Reunión Temporal ===\n\n";

class DebugHelper {
    use MeetingContentParsing;

    public function debugDecryption($content) {
        return $this->decryptJuFile($content);
    }

    public function debugExtraction($data) {
        return $this->extractMeetingDataFromJson($data);
    }
}

$helper = new DebugHelper();

// Obtener la reunión
$meeting = TranscriptionTemp::find(11);

if (!$meeting) {
    echo "❌ Reunión no encontrada\n";
    exit;
}

echo "✅ Reunión encontrada: {$meeting->title}\n";
echo "📁 Archivo de transcripción: {$meeting->transcription_path}\n";

if (!Storage::disk('local')->exists($meeting->transcription_path)) {
    echo "❌ Archivo no existe\n";
    exit;
}

// Leer contenido raw
$content = Storage::disk('local')->get($meeting->transcription_path);
echo "📄 Tamaño del archivo: " . strlen($content) . " bytes\n";
echo "🔍 Primeros 100 caracteres: " . substr($content, 0, 100) . "\n\n";

// Intentar desencriptar
echo "🔓 Intentando desencriptar...\n";
try {
    $result = $helper->debugDecryption($content);
    echo "✅ Desencriptación exitosa\n";
    echo "📊 Datos disponibles: " . json_encode(array_keys($result), JSON_PRETTY_PRINT) . "\n";

    if (isset($result['data'])) {
        echo "\n🎯 Procesando datos extraídos...\n";
        $extractedData = $helper->debugExtraction($result['data']);

        echo "📋 Campos extraídos:\n";
        foreach ($extractedData as $key => $value) {
            if (is_array($value)) {
                echo "  - {$key}: " . count($value) . " elementos\n";
                if ($key === 'tasks' && count($value) > 0) {
                    echo "    Tareas encontradas:\n";
                    foreach ($value as $index => $task) {
                        if (is_string($task)) {
                            echo "      " . ($index + 1) . ". {$task}\n";
                        } elseif (is_array($task)) {
                            $taskText = $task['tarea'] ?? $task['text'] ?? $task['title'] ?? 'Sin texto';
                            echo "      " . ($index + 1) . ". {$taskText}\n";
                            if (isset($task['descripcion'])) {
                                echo "         Descripción: {$task['descripcion']}\n";
                            }
                        }
                    }
                }
            } else {
                $displayValue = is_string($value) ?
                    (strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value) :
                    json_encode($value);
                echo "  - {$key}: {$displayValue}\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ Error en desencriptación: " . $e->getMessage() . "\n";

    // Intentar leer como JSON directo
    echo "\n🔄 Intentando como JSON directo...\n";
    $directJson = json_decode($content, true);
    if ($directJson) {
        echo "✅ Es JSON válido sin encriptar\n";
        echo "📋 Campos disponibles: " . json_encode(array_keys($directJson), JSON_PRETTY_PRINT) . "\n";

        if (isset($directJson['tasks'])) {
            echo "📝 Tareas encontradas: " . count($directJson['tasks']) . "\n";
            foreach ($directJson['tasks'] as $index => $task) {
                echo "  " . ($index + 1) . ". " . (is_string($task) ? $task : json_encode($task)) . "\n";
            }
        }
    } else {
        echo "❌ No es JSON válido\n";

        // Mostrar más información sobre el formato
        echo "🔍 Análisis del formato:\n";
        echo "  - Inicia con: " . substr($content, 0, 10) . "\n";
        echo "  - Termina con: " . substr($content, -10) . "\n";
        echo "  - Contiene '{': " . (strpos($content, '{') !== false ? 'Sí' : 'No') . "\n";
        echo "  - Es base64: " . (base64_encode(base64_decode($content, true)) === $content ? 'Posible' : 'No') . "\n";
    }
}

echo "\nDebug completado.\n";
