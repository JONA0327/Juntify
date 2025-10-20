<?php

require_once __DIR__ . '/bootstrap/app.php';

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\AiDocument;
use App\Services\ExtractorService;
use App\Jobs\ProcessAiDocumentJob;

echo "=== PROCESANDO DOCUMENTO TEMPORAL ===\n";

$doc = AiDocument::find(56);
if (!$doc) {
    echo "❌ Documento no encontrado\n";
    exit(1);
}

echo "Documento encontrado: {$doc->original_filename}\n";

// Actualizar a temporal
$doc->is_temporary = true;
$doc->drive_type = 'temporary';
$doc->processing_status = 'pending';
$doc->save();

echo "✅ Documento marcado como temporal\n";

// Obtener contenido del archivo desde Google Drive
try {
    echo "Descargando archivo desde Google Drive...\n";
    $driveService = app(\App\Services\GoogleDriveService::class);
    $content = $driveService->downloadFile($doc->drive_file_id);

    if ($content) {
        echo "✅ Archivo descargado (" . strlen($content) . " bytes)\n";

        // Actualizar metadata con el contenido
        $metadata = $doc->document_metadata ?? [];
        $metadata['file_content'] = base64_encode($content);
        $metadata['temporary_file'] = true;
        $metadata['converted_from_permanent'] = true;
        $doc->document_metadata = $metadata;
        $doc->save();

        echo "✅ Contenido guardado en metadata\n";

        // Procesar el documento
        echo "Procesando contenido...\n";
        $extractorService = app(ExtractorService::class);

        // Determinar el tipo de archivo
        $extension = strtolower(pathinfo($doc->original_filename, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            echo "Procesando PDF...\n";
            $extractedText = $extractorService->extractFromPdf($content);
        } else {
            echo "Tipo de archivo no soportado para extracción directa: {$extension}\n";
            $extractedText = "Archivo tipo {$extension} - requiere procesamiento específico";
        }

        if ($extractedText && strlen($extractedText) > 10) {
            $doc->extracted_text = $extractedText;
            $doc->processing_status = 'completed';
            $doc->processing_progress = 100;
            $doc->save();

            echo "✅ Texto extraído (" . strlen($extractedText) . " caracteres)\n";
            echo "Vista previa: " . substr($extractedText, 0, 200) . "...\n";
        } else {
            echo "⚠️ No se pudo extraer texto del documento\n";
            $doc->processing_status = 'failed';
            $doc->processing_error = 'No se pudo extraer texto del documento';
            $doc->save();
        }

    } else {
        echo "❌ No se pudo descargar el archivo desde Google Drive\n";
    }

} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    $doc->processing_status = 'failed';
    $doc->processing_error = $e->getMessage();
    $doc->save();
}

echo "\n=== PROCESO COMPLETADO ===\n";
