<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $userDir = "temp_transcriptions/a2c8514d-932c-4bc9-8a2b-e7355faa25ad";
    $files = glob(storage_path("app/{$userDir}") . "/*kualifin*.ju");

    if (!empty($files)) {
        $latestFile = end($files);
        $content = file_get_contents($latestFile);

        // Descifrar
        $googleDriveService = app(\App\Services\GoogleDriveService::class);
        $googleTokenRefreshService = app(\App\Services\GoogleTokenRefreshService::class);
        $aiController = new \App\Http\Controllers\AiAssistantController($googleDriveService, $googleTokenRefreshService);

        $reflection = new ReflectionClass($aiController);
        $decryptMethod = $reflection->getMethod('decryptJuFile');
        $decryptMethod->setAccessible(true);

        $decrypted = $decryptMethod->invoke($aiController, $content);
        $data = $decrypted['data'];

        echo "=== ESTRUCTURA COMPLETA DEL ARCHIVO .JU ===\n\n";
        echo "Claves principales:\n";
        foreach (array_keys($data) as $key) {
            echo "- {$key}\n";
        }

        echo "\n=== TAREAS ===\n";
        if (isset($data['tasks'])) {
            echo "Tipo de tasks: " . gettype($data['tasks']) . "\n";
            echo "Contenido de tasks:\n";
            var_dump($data['tasks']);
        } else {
            echo "No hay campo 'tasks'\n";
        }

        // Buscar campos que puedan contener tareas
        foreach ($data as $key => $value) {
            if (is_array($value) && str_contains(strtolower($key), 'task')) {
                echo "\nCampo '{$key}' (posibles tareas):\n";
                var_dump($value);
            }
        }

        echo "\n=== TRANSCRIPCIÓN ===\n";
        if (isset($data['transcription'])) {
            echo "Tipo: " . gettype($data['transcription']) . "\n";
            echo "Longitud: " . strlen($data['transcription']) . " chars\n";
            echo "Primeros 500 chars:\n" . substr($data['transcription'], 0, 500) . "\n";
        }

        echo "\n=== ESTRUCTURA SEGMENTS ===\n";
        if (isset($data['segments'])) {
            echo "Número de segmentos: " . count($data['segments']) . "\n";
            if (!empty($data['segments'])) {
                echo "Primer segmento:\n";
                var_dump($data['segments'][0]);
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
