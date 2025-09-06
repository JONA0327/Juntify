<?php

// Script para diagnosticar transcripciones WebM
// Uso: php debug_webm_transcription.php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

$apiKey = config('services.assemblyai.api_key');

if (empty($apiKey)) {
    echo "Error: ASSEMBLYAI_API_KEY no está configurada\n";
    exit(1);
}

echo "=== DIAGNÓSTICO DE TRANSCRIPCIÓN WebM ===\n\n";

// Buscar el último tracking ID en logs
$logFile = storage_path('logs/laravel.log');
$logs = file_get_contents($logFile);

// Buscar el último tracking ID de WebM
if (preg_match_all('/tracking_id":"([^"]+)".*WebM/', $logs, $matches)) {
    $trackingId = end($matches[1]);
    echo "Último tracking ID WebM encontrado: $trackingId\n";

    // Buscar transcription_id asociado
    if (preg_match('/transcription_id":"([^"]+)"/', $logs, $transcriptionMatches)) {
        $transcriptionId = $transcriptionMatches[1];
        echo "Transcription ID: $transcriptionId\n\n";

        // Consultar estado en AssemblyAI
        $response = Http::withHeaders([
            'authorization' => $apiKey,
        ])->get("https://api.assemblyai.com/v2/transcript/$transcriptionId");

        if ($response->successful()) {
            $data = $response->json();

            echo "=== ESTADO DE LA TRANSCRIPCIÓN ===\n";
            echo "Status: " . $data['status'] . "\n";
            echo "Audio URL: " . ($data['audio_url'] ?? 'N/A') . "\n";
            echo "Language: " . ($data['language_code'] ?? 'N/A') . "\n";
            echo "Speed Boost: " . ($data['speed_boost'] ? 'SÍ' : 'NO') . "\n";
            echo "Audio Duration: " . ($data['audio_duration'] ?? 'N/A') . " segundos\n";

            if (isset($data['text'])) {
                $textLength = strlen($data['text']);
                $wordCount = str_word_count($data['text']);
                echo "Texto generado: $textLength caracteres, $wordCount palabras\n";

                // Estimar duración basada en palabras (promedio 150 palabras por minuto)
                $estimatedMinutes = $wordCount / 150;
                echo "Duración estimada basada en palabras: " . round($estimatedMinutes, 1) . " minutos\n";

                // Primeras y últimas palabras para verificar si está completo
                $words = explode(' ', $data['text']);
                if (count($words) > 20) {
                    echo "Primeras 10 palabras: " . implode(' ', array_slice($words, 0, 10)) . "...\n";
                    echo "Últimas 10 palabras: ..." . implode(' ', array_slice($words, -10)) . "\n";
                }
            }

            // Configuraciones aplicadas
            echo "\n=== CONFIGURACIONES APLICADAS ===\n";
            foreach (['speed_boost', 'dual_channel', 'filter_profanity', 'speaker_labels', 'format_text', 'audio_start_from', 'audio_end_at'] as $config) {
                if (isset($data[$config])) {
                    echo "$config: " . ($data[$config] === null ? 'null' : ($data[$config] ? 'true' : 'false')) . "\n";
                }
            }

            // Si hay error
            if (isset($data['error'])) {
                echo "\n=== ERROR ===\n";
                echo $data['error'] . "\n";
            }

        } else {
            echo "Error al consultar AssemblyAI: " . $response->status() . "\n";
            echo $response->body() . "\n";
        }
    } else {
        echo "No se encontró transcription_id en los logs\n";
    }
} else {
    echo "No se encontraron transcripciones WebM recientes en los logs\n";
}

echo "\n=== CONFIGURACIÓN ACTUAL ===\n";
echo "Timeout configurado: " . config('services.assemblyai.timeout', 300) . " segundos\n";
echo "SSL verificación: " . (config('services.assemblyai.verify_ssl', true) ? 'SÍ' : 'NO') . "\n";
