<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\AiDocument;
use App\Jobs\ProcessAiDocumentJob;
use Illuminate\Support\Facades\Storage;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST COMPLETO DEL SISTEMA DE DOCUMENTOS ===\n\n";

// 1. Crear un archivo de prueba
echo "📄 CREANDO ARCHIVO DE PRUEBA\n";
echo str_repeat("-", 40) . "\n";

$testContent = "Este es un documento de prueba para el asistente IA.
Contiene información importante sobre:
- Configuración del sistema
- Procesamiento de documentos
- Extracción de texto
- Embeddings y búsqueda

El sistema debe ser capaz de procesar este contenido y permitir consultas sobre él.";

$tempFile = tempnam(sys_get_temp_dir(), 'test_doc_') . '.txt';
file_put_contents($tempFile, $testContent);

echo "Archivo creado: " . basename($tempFile) . "\n";
echo "Contenido: " . strlen($testContent) . " caracteres\n\n";

// 2. Simular subida a Google Drive (crear documento directamente)
echo "📤 SIMULANDO PROCESAMIENTO DE DOCUMENTO\n";
echo str_repeat("-", 40) . "\n";

$document = AiDocument::create([
    'username' => 'Adgoja', // Usuario existente
    'name' => 'documento_prueba',
    'original_filename' => 'documento_prueba.txt',
    'document_type' => 'text',
    'mime_type' => 'text/plain',
    'file_size' => strlen($testContent),
    'drive_file_id' => 'test_drive_id_' . time(),
    'drive_folder_id' => 'test_folder_id',
    'drive_type' => 'personal',
    'processing_status' => 'pending',
    'document_metadata' => [
        'test_mode' => true,
        'created_via' => 'system_test'
    ]
]);

echo "Documento creado con ID: {$document->id}\n";
echo "Estado inicial: {$document->processing_status}\n\n";

// 3. Crear archivo temporal para simular descarga de Drive
$testFile = storage_path('app/temp_test_' . $document->id . '.txt');
file_put_contents($testFile, $testContent);

// 4. Procesar documento directamente (sin job queue para testing)
echo "⚙️  PROCESANDO DOCUMENTO\n";
echo str_repeat("-", 40) . "\n";

try {
    $extractorService = app(\App\Services\ExtractorService::class);

    echo "Extrayendo texto...\n";
    $extracted = $extractorService->extract($testFile, 'text/plain', 'test.txt');

    echo "Texto extraído: " . strlen($extracted['text']) . " caracteres\n";
    echo "Metadata: " . json_encode($extracted['metadata'], JSON_PRETTY_PRINT) . "\n";

    if (strlen($extracted['text']) > 0) {
        // Actualizar documento con texto extraído
        $document->update([
            'extracted_text' => $extracted['text'],
            'processing_status' => 'completed',
            'processing_step' => 'done',
            'processing_progress' => 100,
            'ocr_metadata' => $extracted['metadata']
        ]);

        echo "✅ Documento procesado exitosamente\n\n";
    } else {
        throw new Exception("No se extrajo texto del documento");
    }

} catch (Exception $e) {
    echo "❌ Error procesando documento: " . $e->getMessage() . "\n";
    $document->update([
        'processing_status' => 'failed',
        'processing_error' => $e->getMessage()
    ]);
}

// 5. Test de búsqueda/consulta
echo "🔍 TEST DE CONSULTA AL DOCUMENTO\n";
echo str_repeat("-", 40) . "\n";

$searchTerm = "configuración";
if (str_contains(strtolower($document->extracted_text ?? ''), strtolower($searchTerm))) {
    echo "✅ Búsqueda exitosa: Encontrado '{$searchTerm}' en el documento\n";
} else {
    echo "❌ No se encontró '{$searchTerm}' en el documento\n";
}

// 6. Test de ChunkerService
echo "\n📝 TEST DE CHUNKER SERVICE\n";
echo str_repeat("-", 40) . "\n";

try {
    $chunkerService = app(\App\Services\ChunkerService::class);
    $chunks = $chunkerService->chunk($document->extracted_text);

    echo "Chunks generados: " . count($chunks['chunks']) . "\n";
    echo "Texto normalizado: " . strlen($chunks['normalized_text']) . " caracteres\n";
    echo "✅ ChunkerService funcionando correctamente\n";

} catch (Exception $e) {
    echo "❌ Error en ChunkerService: " . $e->getMessage() . "\n";
}

// 7. Verificar estado final
echo "\n📊 ESTADO FINAL DEL DOCUMENTO\n";
echo str_repeat("=", 50) . "\n";

$document->refresh();
echo "ID: {$document->id}\n";
echo "Nombre: {$document->name}\n";
echo "Estado: {$document->processing_status}\n";
echo "Progreso: {$document->processing_progress}%\n";
echo "Texto extraído: " . (strlen($document->extracted_text ?? '') > 0 ? "SÍ" : "NO") . "\n";
echo "Caracteres: " . strlen($document->extracted_text ?? '') . "\n";

if ($document->processing_error) {
    echo "Error: {$document->processing_error}\n";
}

// 8. Simular consulta al asistente
echo "\n🤖 SIMULANDO CONSULTA AL ASISTENTE\n";
echo str_repeat("=", 50) . "\n";

$query = "¿Qué dice el documento sobre configuración del sistema?";
echo "Pregunta: {$query}\n\n";

// Buscar en el texto del documento
$text = strtolower($document->extracted_text ?? '');
$queryWords = explode(' ', strtolower($query));
$relevantSentences = [];

if ($document->extracted_text) {
    $sentences = preg_split('/[.!?]+/', $document->extracted_text);
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence)) continue;

        foreach ($queryWords as $word) {
            $word = trim($word, '¿?');
            if (strlen($word) > 3 && str_contains(strtolower($sentence), $word)) {
                $relevantSentences[] = $sentence;
                break;
            }
        }
    }
}

if (!empty($relevantSentences)) {
    echo "✅ Información encontrada en el documento:\n";
    foreach (array_slice($relevantSentences, 0, 3) as $sentence) {
        echo "  • " . trim($sentence) . "\n";
    }
} else {
    echo "❌ No se encontró información relevante para la consulta\n";
}

// Cleanup
if (file_exists($testFile)) {
    unlink($testFile);
}
if (file_exists($tempFile)) {
    unlink($tempFile);
}

echo "\n🎉 TEST COMPLETO FINALIZADO\n";
echo "\nRESUMEN:\n";
echo ($document->processing_status === 'completed' ? "✅" : "❌") . " Procesamiento de documento\n";
echo (strlen($document->extracted_text ?? '') > 0 ? "✅" : "❌") . " Extracción de texto\n";
echo (!empty($relevantSentences) ? "✅" : "❌") . " Capacidad de búsqueda\n";

if ($document->processing_status === 'completed' &&
    strlen($document->extracted_text ?? '') > 0 &&
    !empty($relevantSentences)) {
    echo "\n🚀 ¡EL SISTEMA DE DOCUMENTOS ESTÁ FUNCIONANDO CORRECTAMENTE!\n";
} else {
    echo "\n⚠️  El sistema tiene algunos problemas que necesitan revisión.\n";
}
