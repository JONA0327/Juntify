<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

$trackingId = '14ec7bad-bc3b-4710-b997-fe26955e850d';
$cacheKey = "chunked_transcription:{$trackingId}";

echo "=== VERIFICACIÓN DE TRANSCRIPCIÓN WebM ===\n\n";
echo "Tracking ID: $trackingId\n";

// Verificar estado en cache
$cacheData = Cache::get($cacheKey);
if ($cacheData) {
    echo "Estado en cache: " . json_encode($cacheData, JSON_PRETTY_PRINT) . "\n\n";

    if (isset($cacheData['transcription_id'])) {
        $transcriptionId = $cacheData['transcription_id'];
        echo "Transcription ID encontrado: $transcriptionId\n";

        // Consultar estado en AssemblyAI
        $apiKey = config('services.assemblyai.api_key');
        $response = Http::withHeaders([
            'authorization' => $apiKey,
        ])->get("https://api.assemblyai.com/v2/transcript/$transcriptionId");

        if ($response->successful()) {
            $data = $response->json();
            echo "\n=== ESTADO EN ASSEMBLYAI ===\n";
            echo "Status: " . $data['status'] . "\n";

            if (isset($data['audio_duration'])) {
                echo "Duración del audio: " . round($data['audio_duration'] / 60, 2) . " minutos\n";
            }

            if (isset($data['text'])) {
                $wordCount = str_word_count($data['text']);
                echo "Palabras transcritas: $wordCount\n";
                $estimatedMinutes = $wordCount / 150; // promedio palabras por minuto
                echo "Duración estimada: " . round($estimatedMinutes, 2) . " minutos\n";

                // Mostrar inicio y final del texto
                if (strlen($data['text']) > 200) {
                    echo "\nInicio del texto: " . substr($data['text'], 0, 100) . "...\n";
                    echo "Final del texto: ..." . substr($data['text'], -100) . "\n";
                }
            }

            if (isset($data['error'])) {
                echo "\nERROR: " . $data['error'] . "\n";
            }

            // Mostrar configuraciones importantes
            echo "\n=== CONFIGURACIONES ===\n";
            echo "Speed boost: " . ($data['speed_boost'] ?? 'null') . "\n";
            echo "Audio end at: " . ($data['audio_end_at'] ?? 'null') . "\n";
            echo "Language code: " . ($data['language_code'] ?? 'null') . "\n";

        } else {
            echo "Error al consultar AssemblyAI: " . $response->status() . "\n";
        }
    }
} else {
    echo "No se encontró información en cache para este tracking ID\n";
}

echo "\nVerificando logs de error...\n";
$logFile = storage_path('logs/laravel.log');
$logs = file_get_contents($logFile);

// Buscar errores relacionados con este tracking ID
if (strpos($logs, $trackingId) !== false) {
    echo "Tracking ID encontrado en logs.\n";

    // Buscar líneas con error
    $logLines = explode("\n", $logs);
    foreach ($logLines as $line) {
        if (strpos($line, $trackingId) !== false && strpos($line, 'ERROR') !== false) {
            echo "ERROR: $line\n";
        }
    }
} else {
    echo "Tracking ID no encontrado en logs.\n";
}
