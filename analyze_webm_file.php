<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ANÁLISIS DEL ARCHIVO WebM ===\n\n";

// Buscar archivos temporales recientes
$tempDir = storage_path('app/temp-uploads');
echo "Buscando archivos en: $tempDir\n";

if (!is_dir($tempDir)) {
    echo "Directorio temporal no existe.\n";
    exit;
}

$uploadDirs = glob($tempDir . '/*', GLOB_ONLYDIR);
usort($uploadDirs, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

echo "Directorios encontrados: " . count($uploadDirs) . "\n";

foreach (array_slice($uploadDirs, 0, 3) as $dir) {
    echo "\n--- Directorio: " . basename($dir) . " ---\n";
    echo "Modificado: " . date('Y-m-d H:i:s', filemtime($dir)) . "\n";

    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $size = filesize($file);
            echo basename($file) . ": " . number_format($size) . " bytes (" . round($size/1024/1024, 2) . " MB)\n";

            // Si es el archivo final
            if (strpos(basename($file), 'final_audio') !== false) {
                echo "  → Archivo combinado final encontrado\n";

                // Intentar verificar la duración con ffprobe si está disponible
                $ffprobeOutput = shell_exec("ffprobe -v quiet -show_entries format=duration -of csv=p=0 \"$file\" 2>&1");
                if ($ffprobeOutput && is_numeric(trim($ffprobeOutput))) {
                    $duration = floatval(trim($ffprobeOutput));
                    echo "  → Duración real: " . round($duration/60, 2) . " minutos\n";
                } else {
                    echo "  → No se pudo determinar la duración (ffprobe no disponible)\n";
                }
            }
        }
    }

    // Leer metadata si existe
    $metadataFile = $dir . '/metadata.json';
    if (file_exists($metadataFile)) {
        $metadata = json_decode(file_get_contents($metadataFile), true);
        echo "Metadata:\n";
        echo "  - Archivo original: " . $metadata['filename'] . "\n";
        echo "  - Tamaño esperado: " . number_format($metadata['total_size']) . " bytes\n";
        echo "  - Chunks esperados: " . $metadata['chunks_expected'] . "\n";
        echo "  - Chunks recibidos: " . $metadata['chunks_received'] . "\n";
    }
}

// Verificar si hay archivos WebM recientes en storage
echo "\n=== ARCHIVOS WebM EN STORAGE ===\n";
$storageFiles = glob(storage_path('app') . '/**/*.webm', GLOB_BRACE);
if ($storageFiles) {
    foreach ($storageFiles as $file) {
        $size = filesize($file);
        echo basename($file) . ": " . number_format($size) . " bytes (" . round($size/1024/1024, 2) . " MB)\n";
        echo "  Modificado: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
    }
} else {
    echo "No se encontraron archivos WebM en storage.\n";
}

echo "\n=== RECOMENDACIONES ===\n";
echo "1. Verifica que el archivo original WebM realmente dure 1:09 horas\n";
echo "2. Prueba reproducir el archivo WebM localmente para confirmar su duración\n";
echo "3. Si el archivo está truncado, el problema está en la grabación, no en la transcripción\n";
echo "4. Considera usar un formato más estable como MP3 para archivos largos\n";
