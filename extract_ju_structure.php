<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "📄 EXTRACTOR DE ESTRUCTURA .JU - ARCHIVO CRUDO BNI\n";
echo "==================================================\n\n";

// 1. Buscar el usuario y reunión BNI
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();
$meeting = App\Models\TranscriptionTemp::where('user_id', $user->id)->first();

echo "📋 DATOS DE LA REUNIÓN:\n";
echo "   - Usuario: {$user->email}\n";
echo "   - Reunión ID: {$meeting->id}\n";
echo "   - Título: {$meeting->title}\n";
echo "   - Fecha: {$meeting->created_at}\n\n";

// 2. Verificar archivos
echo "📁 ARCHIVOS ALMACENADOS:\n";
echo "   - Transcripción path: {$meeting->transcription_path}\n";
echo "   - Audio path: {$meeting->audio_path}\n";

$transcriptionExists = !empty($meeting->transcription_path) &&
                      \Illuminate\Support\Facades\Storage::disk('local')->exists($meeting->transcription_path);
$audioExists = !empty($meeting->audio_path) &&
               \Illuminate\Support\Facades\Storage::disk('local')->exists($meeting->audio_path);

echo "   - Transcripción existe: " . ($transcriptionExists ? '✅' : '❌') . "\n";
echo "   - Audio existe: " . ($audioExists ? '✅' : '❌') . "\n\n";

// 3. Extraer el contenido del .ju real
if ($transcriptionExists) {
    echo "📄 EXTRAYENDO CONTENIDO .JU REAL...\n";

    $transcriptionContent = \Illuminate\Support\Facades\Storage::disk('local')->get($meeting->transcription_path);
    $transcriptionSize = strlen($transcriptionContent);

    echo "   - Tamaño archivo: " . number_format($transcriptionSize) . " bytes\n";

    // Verificar si es JSON válido
    $jsonData = json_decode($transcriptionContent, true);
    $isValidJson = $jsonData !== null;

    echo "   - Es JSON válido: " . ($isValidJson ? '✅' : '❌') . "\n";

    if ($isValidJson) {
        echo "   - Estructura JSON detectada ✅\n\n";

        // Mostrar estructura completa
        echo "🏗️  ESTRUCTURA COMPLETA DEL ARCHIVO .JU:\n";
        echo "=======================================\n";

        // Función para mostrar estructura recursiva
        function showStructure($data, $indent = 0) {
            $spaces = str_repeat('  ', $indent);

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        echo "{$spaces}{$key}: {\n";
                        showStructure($value, $indent + 1);
                        echo "{$spaces}}\n";
                    } else {
                        $type = gettype($value);
                        $preview = is_string($value) ?
                            (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) :
                            $value;
                        echo "{$spaces}{$key}: ({$type}) {$preview}\n";
                    }
                }
            } elseif (is_object($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value) || is_object($value)) {
                        echo "{$spaces}{$key}: {\n";
                        showStructure($value, $indent + 1);
                        echo "{$spaces}}\n";
                    } else {
                        $type = gettype($value);
                        $preview = is_string($value) ?
                            (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) :
                            $value;
                        echo "{$spaces}{$key}: ({$type}) {$preview}\n";
                    }
                }
            }
        }

        showStructure($jsonData);

        // Guardar estructura en archivo
        echo "\n📁 GUARDANDO ESTRUCTURA EN ARCHIVO...\n";

        // Crear versión formateada para lectura
        $formattedContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Guardar archivo crudo completo
        $rawFileName = "estructura_ju_bni_crudo_completo.json";
        file_put_contents($rawFileName, $formattedContent);
        echo "   - Archivo completo guardado: {$rawFileName}\n";

        // Crear versión con solo estructura (sin contenido largo)
        $structureOnly = createStructureOnly($jsonData);
        $structureContent = json_encode($structureOnly, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $structureFileName = "estructura_ju_bni_esquema.json";
        file_put_contents($structureFileName, $structureContent);
        echo "   - Esquema guardado: {$structureFileName}\n";

        // Crear documentación
        $docContent = "# ESTRUCTURA DEL ARCHIVO .JU - USUARIO BNI\n\n";
        $docContent .= "## Información General\n";
        $docContent .= "- **Usuario**: {$user->email}\n";
        $docContent .= "- **Reunión**: {$meeting->title}\n";
        $docContent .= "- **ID**: {$meeting->id}\n";
        $docContent .= "- **Fecha**: {$meeting->created_at}\n";
        $docContent .= "- **Tipo**: Almacenamiento temporal BNI\n";
        $docContent .= "- **Tamaño**: " . number_format($transcriptionSize) . " bytes\n\n";

        $docContent .= "## Características BNI\n";
        $docContent .= "- ✅ **Sin encriptación**: El archivo es JSON legible directamente\n";
        $docContent .= "- ✅ **Almacenamiento temporal**: No se guarda en Google Drive\n";
        $docContent .= "- ✅ **Auto-descarga**: Se descarga automáticamente\n";
        $docContent .= "- ✅ **Acceso ilimitado**: Sin restricciones de plan\n\n";

        $docContent .= "## Estructura de Campos\n";
        generateFieldDocumentation($jsonData, $docContent, 0);

        $docFileName = "documentacion_ju_bni.md";
        file_put_contents($docFileName, $docContent);
        echo "   - Documentación guardada: {$docFileName}\n\n";

        echo "📊 RESUMEN DE LA ESTRUCTURA:\n";
        echo "============================\n";
        echo "- Campos principales: " . count($jsonData) . "\n";
        echo "- Formato: JSON sin encriptar\n";
        echo "- Encoding: UTF-8\n";
        echo "- Tipo de contenido: application/json\n";
        echo "- Legible directamente: ✅ SÍ\n\n";

    } else {
        echo "   ❌ El archivo no es JSON válido\n";
        echo "   Mostrando contenido crudo (primeros 1000 caracteres):\n\n";
        echo substr($transcriptionContent, 0, 1000);

        // Guardar contenido crudo
        $rawFileName = "archivo_ju_bni_crudo.txt";
        file_put_contents($rawFileName, $transcriptionContent);
        echo "\n\n📁 Contenido crudo guardado en: {$rawFileName}\n";
    }
} else {
    echo "❌ No se puede extraer el archivo .ju - no existe\n";
}

// Función para crear solo estructura sin contenido largo
function createStructureOnly($data) {
    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $result[$key] = createStructureOnly($value);
            } else {
                $type = gettype($value);
                if (is_string($value) && strlen($value) > 100) {
                    $result[$key] = "({$type}) [" . strlen($value) . " caracteres] " . substr($value, 0, 50) . "...";
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    } elseif (is_object($data)) {
        $result = new stdClass();
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $result->$key = createStructureOnly($value);
            } else {
                $type = gettype($value);
                if (is_string($value) && strlen($value) > 100) {
                    $result->$key = "({$type}) [" . strlen($value) . " caracteres] " . substr($value, 0, 50) . "...";
                } else {
                    $result->$key = $value;
                }
            }
        }
        return $result;
    }
    return $data;
}

// Función para generar documentación de campos
function generateFieldDocumentation($data, &$docContent, $level) {
    $indent = str_repeat('  ', $level);

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $docContent .= "{$indent}- **{$key}**";

            if (is_array($value) || is_object($value)) {
                $docContent .= " (objeto/array)\n";
                generateFieldDocumentation($value, $docContent, $level + 1);
            } else {
                $type = gettype($value);
                $length = is_string($value) ? " (" . strlen($value) . " chars)" : "";
                $docContent .= ": {$type}{$length}\n";
            }
        }
    }
}

echo "✨ ¡EXTRACCIÓN COMPLETADA!\n";
echo "\nArchivos generados:\n";
echo "- estructura_ju_bni_crudo_completo.json (archivo completo)\n";
echo "- estructura_ju_bni_esquema.json (solo estructura)\n";
echo "- documentacion_ju_bni.md (documentación completa)\n";
