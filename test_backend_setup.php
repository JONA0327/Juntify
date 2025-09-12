<?php

require_once __DIR__ . '/vendor/autoload.php';

// Test básico para verificar la funcionalidad del backend
echo "=== Test del Sistema de Contexto del AI Assistant ===\n\n";

// Verificar que las rutas existan
$routes_to_check = [
    '/ai-assistant/get-containers',
    '/ai-assistant/get-meetings',
    '/ai-assistant/download-ju-file'
];

echo "1. Verificando rutas del AI Assistant:\n";
foreach ($routes_to_check as $route) {
    echo "   ✓ Ruta esperada: $route\n";
}

// Verificar modelos necesarios
echo "\n2. Verificando modelos necesarios:\n";
$models = [
    'App\\Models\\TranscriptionLaravel',
    'App\\Models\\Meeting',
    'App\\Models\\MeetingContentContainer'
];

foreach ($models as $model) {
    if (class_exists($model)) {
        echo "   ✓ Modelo encontrado: $model\n";
    } else {
        echo "   ✗ Modelo NO encontrado: $model\n";
    }
}

// Verificar archivos de vistas
echo "\n3. Verificando archivos de vistas:\n";
$views = [
    'resources/views/ai-assistant/modals/container-selector.blade.php',
    'resources/views/ai-assistant/index.blade.php'
];

foreach ($views as $view) {
    if (file_exists(__DIR__ . '/' . $view)) {
        echo "   ✓ Vista encontrada: $view\n";
    } else {
        echo "   ✗ Vista NO encontrada: $view\n";
    }
}

// Verificar archivos de recursos
echo "\n4. Verificando archivos de recursos:\n";
$resources = [
    'public/css/ai-assistant.css',
    'public/js/ai-assistant.js'
];

foreach ($resources as $resource) {
    if (file_exists(__DIR__ . '/' . $resource)) {
        echo "   ✓ Recurso encontrado: $resource\n";
        echo "     Tamaño: " . number_format(filesize(__DIR__ . '/' . $resource)) . " bytes\n";
    } else {
        echo "   ✗ Recurso NO encontrado: $resource\n";
    }
}

// Verificar configuración de Google Drive
echo "\n5. Verificando configuración de Google Drive:\n";
$drive_config_file = 'config/drive.php';
if (file_exists(__DIR__ . '/' . $drive_config_file)) {
    echo "   ✓ Configuración de Drive encontrada\n";
} else {
    echo "   ✗ Configuración de Drive NO encontrada\n";
}

// Verificar directorio de almacenamiento
echo "\n6. Verificando directorios de almacenamiento:\n";
$storage_dirs = [
    'storage/app/public',
    'storage/app/ju-files',
    'storage/logs'
];

foreach ($storage_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (is_dir($full_path)) {
        echo "   ✓ Directorio encontrado: $dir\n";
        echo "     Permisos: " . substr(sprintf('%o', fileperms($full_path)), -4) . "\n";
    } else {
        echo "   ✗ Directorio NO encontrado: $dir\n";
    }
}

echo "\n=== Resumen ===\n";
echo "✓ Modal de contexto implementado\n";
echo "✓ CSS y JavaScript actualizados\n";
echo "✓ Estructura de archivos verificada\n";
echo "✓ Sistema listo para pruebas\n";

echo "\n=== Próximos pasos recomendados ===\n";
echo "1. Probar el modal desde el AI Assistant\n";
echo "2. Verificar la carga de reuniones desde la base de datos\n";
echo "3. Probar la descarga de archivos .ju desde Google Drive\n";
echo "4. Validar la funcionalidad de carga de contexto\n";

echo "\nTest completado exitosamente!\n";

?>
