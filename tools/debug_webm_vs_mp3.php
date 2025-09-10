<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIAGNÓSTICO DETALLADO WebM vs MP3 ===\n\n";

// Función para analizar un archivo de audio
function analyzeAudioFile($filePath, $type = 'unknown') {
    if (!file_exists($filePath)) {
        echo "❌ Archivo no encontrado: $filePath\n";
        return;
    }

    $size = filesize($filePath);
    $sizeMB = round($size / 1024 / 1024, 2);

    echo "📁 Archivo $type:\n";
    echo "   Ruta: $filePath\n";
    echo "   Tamaño: " . number_format($size) . " bytes ({$sizeMB} MB)\n";

    // Intentar obtener duración con ffprobe si está disponible
    $ffprobeCommand = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 \"$filePath\" 2>&1";
    $durationOutput = shell_exec($ffprobeCommand);

    if ($durationOutput && is_numeric(trim($durationOutput))) {
        $duration = floatval(trim($durationOutput));
        $minutes = round($duration / 60, 2);
        echo "   Duración (ffprobe): {$minutes} minutos\n";
    } else {
        echo "   Duración: No disponible (ffprobe no instalado)\n";
    }

    // Análisis del header del archivo
    $handle = fopen($filePath, 'rb');
    $header = fread($handle, 100);
    fclose($handle);

    if (strpos($header, 'webm') !== false || strpos($header, 'matroska') !== false) {
        echo "   Formato detectado: WebM/Matroska\n";
    } elseif (strpos($header, 'ID3') !== false || strpos($header, 'MPEG') !== false) {
        echo "   Formato detectado: MP3\n";
    } else {
        echo "   Formato: Desconocido\n";
    }

    echo "\n";
}

// Buscar archivos WebM recientes en temp-uploads
$tempDir = storage_path('app/temp-uploads');
echo "🔍 Buscando archivos WebM en: $tempDir\n\n";

if (is_dir($tempDir)) {
    $uploadDirs = glob($tempDir . '/*', GLOB_ONLYDIR);
    usort($uploadDirs, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    foreach (array_slice($uploadDirs, 0, 2) as $dir) {
        echo "📂 Directorio: " . basename($dir) . "\n";
        echo "   Modificado: " . date('Y-m-d H:i:s', filemtime($dir)) . "\n";

        // Buscar archivo final combinado
        $finalFiles = glob($dir . '/final_audio*');
        if ($finalFiles) {
            foreach ($finalFiles as $finalFile) {
                analyzeAudioFile($finalFile, 'WebM combinado');

                // Crear una versión para test con AssemblyAI
                echo "🧪 PRUEBA: Enviando este archivo a AssemblyAI...\n";
                testAssemblyAI($finalFile);
            }
        }

        // Verificar chunks individuales
        $chunks = glob($dir . '/chunk_*');
        if ($chunks) {
            echo "   📦 Chunks encontrados: " . count($chunks) . "\n";
            $totalChunkSize = 0;
            foreach ($chunks as $chunk) {
                $totalChunkSize += filesize($chunk);
            }
            echo "   📦 Tamaño total chunks: " . round($totalChunkSize / 1024 / 1024, 2) . " MB\n";
        }

        echo "\n";
    }
}

function testAssemblyAI($filePath) {
    $apiKey = config('services.assemblyai.api_key');

    if (empty($apiKey)) {
        echo "❌ API Key de AssemblyAI no configurada\n";
        return;
    }

    echo "   📤 Subiendo archivo a AssemblyAI...\n";

    $audioData = file_get_contents($filePath);
    $sizeMB = strlen($audioData) / 1024 / 1024;

    echo "   📊 Datos leídos: " . round($sizeMB, 2) . " MB\n";

    try {
        $uploadResponse = \Illuminate\Support\Facades\Http::withHeaders([
            'authorization' => $apiKey,
            'content-type' => 'application/octet-stream'
        ])
        ->timeout(300)
        ->connectTimeout(60)
        ->withOptions(['verify' => false])
        ->withBody($audioData)
        ->post('https://api.assemblyai.com/v2/upload');

        if ($uploadResponse->successful()) {
            $audioUrl = $uploadResponse->json('upload_url');
            echo "   ✅ Upload exitoso: " . substr($audioUrl, 0, 50) . "...\n";

            // Crear transcripción con configuración mínima
            $payload = [
                'audio_url' => $audioUrl,
                'language_code' => 'es',
                'speaker_labels' => false,
                'punctuate' => true,
                'format_text' => false,
                'speed_boost' => false,
                'audio_end_at' => null,
            ];

            echo "   🔄 Creando transcripción con config mínima...\n";

            $transcriptResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'authorization' => $apiKey,
                'content-type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://api.assemblyai.com/v2/transcript', $payload);

            if ($transcriptResponse->successful()) {
                $transcriptId = $transcriptResponse->json('id');
                echo "   ✅ Transcripción iniciada: $transcriptId\n";
                echo "   📋 Payload enviado: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";

                // Esperar un momento y verificar estado inicial
                sleep(5);

                $statusResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    'authorization' => $apiKey,
                ])->get("https://api.assemblyai.com/v2/transcript/$transcriptId");

                if ($statusResponse->successful()) {
                    $status = $statusResponse->json();
                    echo "   📊 Estado inicial: " . $status['status'] . "\n";
                    if (isset($status['audio_duration'])) {
                        echo "   ⏱️  Duración detectada por AssemblyAI: " . round($status['audio_duration'] / 60, 2) . " minutos\n";
                    }
                }

            } else {
                echo "   ❌ Error creando transcripción: " . $transcriptResponse->status() . "\n";
                echo "   📄 Respuesta: " . $transcriptResponse->body() . "\n";
            }

        } else {
            echo "   ❌ Error en upload: " . $uploadResponse->status() . "\n";
            echo "   📄 Respuesta: " . $uploadResponse->body() . "\n";
        }

    } catch (Exception $e) {
        echo "   ❌ Excepción: " . $e->getMessage() . "\n";
    }
}

echo "\n=== ANÁLISIS COMPARATIVO ===\n";
echo "Si tienes archivos MP3 del mismo audio, ponlos en storage/app/test-audio/\n";
echo "y ejecuta este script nuevamente para compararlos.\n";

$testDir = storage_path('app/test-audio');
if (is_dir($testDir)) {
    $testFiles = glob($testDir . '/*.{mp3,webm}', GLOB_BRACE);
    foreach ($testFiles as $testFile) {
        $ext = pathinfo($testFile, PATHINFO_EXTENSION);
        analyzeAudioFile($testFile, strtoupper($ext) . ' de prueba');
    }
}
