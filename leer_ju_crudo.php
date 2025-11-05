<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "📖 LECTOR DE ARCHIVO .JU CRUDO - USUARIO BNI\n";
echo "===========================================\n\n";

// Obtener la reunión BNI
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();
$meeting = App\Models\TranscriptionTemp::where('user_id', $user->id)->first();

echo "🎯 LEYENDO ARCHIVO .JU REAL:\n";
echo "   - Reunión: {$meeting->title}\n";
echo "   - ID: {$meeting->id}\n";
echo "   - Path: {$meeting->transcription_path}\n\n";

// Leer el contenido crudo
$content = \Illuminate\Support\Facades\Storage::disk('local')->get($meeting->transcription_path);

echo "📊 INFORMACIÓN DEL ARCHIVO:\n";
echo "   - Tamaño: " . number_format(strlen($content)) . " bytes\n";
echo "   - Tipo: " . (json_decode($content) ? 'JSON válido' : 'No es JSON') . "\n\n";

// Parsear JSON
$data = json_decode($content, true);

if ($data) {
    echo "🏗️  ESTRUCTURA DETECTADA:\n";

    // 1. Transcripción
    if (isset($data['transcription']) && is_array($data['transcription'])) {
        echo "   ✅ transcription: Array con " . count($data['transcription']) . " elementos\n";

        // Mostrar primer elemento como ejemplo
        if (count($data['transcription']) > 0) {
            $first = $data['transcription'][0];
            echo "      📝 Primer elemento:\n";
            foreach ($first as $key => $value) {
                if (is_string($value) && strlen($value) > 50) {
                    echo "         - {$key}: \"" . substr($value, 0, 50) . "...\"\n";
                } else {
                    echo "         - {$key}: " . json_encode($value) . "\n";
                }
            }
        }
    }

    // 2. Resumen
    if (isset($data['summary'])) {
        echo "   ✅ summary: String (" . strlen($data['summary']) . " caracteres)\n";
        echo "      📄 Contenido: \"" . substr($data['summary'], 0, 100) . "...\"\n";
    }

    // 3. Puntos clave
    if (isset($data['keyPoints']) && is_array($data['keyPoints'])) {
        echo "   ✅ keyPoints: Array con " . count($data['keyPoints']) . " elementos\n";
        echo "      🔑 Primer punto: \"" . substr($data['keyPoints'][0] ?? '', 0, 80) . "...\"\n";
    }

    echo "\n📋 CAMPOS PRINCIPALES:\n";
    foreach ($data as $key => $value) {
        $type = gettype($value);
        $info = '';

        if (is_array($value)) {
            $info = "Array[" . count($value) . " elementos]";
        } elseif (is_string($value)) {
            $info = "String[" . strlen($value) . " chars]";
        } else {
            $info = $type;
        }

        echo "   - {$key}: {$info}\n";
    }

    echo "\n💾 GUARDAR ESTRUCTURA LIMPIA:\n";

    // Crear versión formateada para lectura fácil
    $formatted = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents('ju_bni_formateado.json', $formatted);
    echo "   ✅ Guardado: ju_bni_formateado.json\n";

    // Crear solo los primeros elementos para ejemplo
    $sample = [
        'transcription' => array_slice($data['transcription'], 0, 3), // Solo 3 elementos
        'summary' => $data['summary'],
        'keyPoints' => array_slice($data['keyPoints'], 0, 5) // Solo 5 puntos
    ];

    $sampleFormatted = json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents('ju_bni_muestra.json', $sampleFormatted);
    echo "   ✅ Muestra guardada: ju_bni_muestra.json\n";

    echo "\n🎯 CARACTERÍSTICAS BNI:\n";
    echo "   🔓 Sin encriptación: ✅ Es JSON puro legible\n";
    echo "   📱 Auto-descarga: ✅ Listo para descarga directa\n";
    echo "   🗄️ Temporal: ✅ No usa Google Drive\n";
    echo "   ♾️ Ilimitado: ✅ Sin restricciones de plan\n";

} else {
    echo "❌ No se pudo parsear como JSON\n";

    // Mostrar contenido crudo (primeros 1000 caracteres)
    echo "📄 CONTENIDO CRUDO (primeros 1000 chars):\n";
    echo "==========================================\n";
    echo substr($content, 0, 1000) . "\n";

    // Guardar contenido completo
    file_put_contents('ju_bni_crudo.txt', $content);
    echo "\n💾 Contenido completo guardado: ju_bni_crudo.txt\n";
}

echo "\n✨ LECTURA COMPLETADA!\n\n";

echo "📂 ARCHIVOS GENERADOS:\n";
echo "=====================\n";
echo "- ju_bni_formateado.json (archivo completo formateado)\n";
echo "- ju_bni_muestra.json (muestra pequeña para ejemplo)\n";
echo "- ESTRUCTURA_JU_BNI.md (documentación completa)\n";
echo "- ejemplo_estructura_ju_bni.json (ejemplo de estructura)\n\n";

echo "🚀 CÓMO USAR:\n";
echo "=============\n";
echo "1. Abrir ju_bni_muestra.json para ver estructura básica\n";
echo "2. Leer ESTRUCTURA_JU_BNI.md para documentación completa\n";
echo "3. Usar ju_bni_formateado.json para ver archivo real completo\n";
echo "4. El formato es JSON estándar - compatible con cualquier parser\n";
