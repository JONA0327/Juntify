<?php

echo "Verificando JavaScript compilado...\n";

$jsPath = 'public/build/assets';
$files = glob($jsPath . '/reuniones_v2-*.js');

if (!empty($files)) {
    $latest = array_reduce($files, function($latest, $file) {
        return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
    });

    $content = file_get_contents($latest);

    echo "📁 Archivo: " . basename($latest) . "\n";
    echo "📅 Modificado: " . date('Y-m-d H:i:s', filemtime($latest)) . "\n";
    echo "📏 Tamaño: " . round(filesize($latest) / 1024, 2) . " KB\n\n";

    // Buscar las correcciones específicas
    $patterns = [
        'meeting_name||meeting.title||' => 'Fallback completo',
        'meeting.meeting_name||meeting.title' => 'Fallback básico',
        'meeting_name||meeting.title' => 'Fallback alternativo'
    ];

    $found = 0;
    foreach ($patterns as $pattern => $description) {
        if (strpos($content, $pattern) !== false) {
            echo "✅ Encontrado: {$description}\n";
            $found++;
        } else {
            echo "❌ No encontrado: {$description}\n";
        }
    }

    echo "\n";

    if ($found > 0) {
        echo "🎉 Se encontraron {$found} correcciones en el JavaScript compilado\n";
        echo "🔄 El usuario debe recargar la página (Ctrl+F5) para ver los cambios\n";
    } else {
        echo "⚠️ Las correcciones no están en el JavaScript compilado\n";
        echo "🔧 Verificar que los cambios se guardaron en resources/js/reuniones_v2.js\n";
    }
} else {
    echo "❌ No se encontraron archivos JavaScript compilados\n";
}
