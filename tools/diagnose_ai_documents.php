<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\AiDocument;
use App\Models\User;
use App\Jobs\ProcessAiDocumentJob;
use App\Services\ExtractorService;
use App\Services\ChunkerService;
use App\Services\GoogleDriveService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DIAGNÓSTICO DEL SISTEMA DE DOCUMENTOS AI ===\n\n";

// 1. Verificar configuraciones
echo "🔧 VERIFICANDO CONFIGURACIONES\n";
echo str_repeat("-", 40) . "\n";
echo "Queue Driver: " . config('queue.default') . "\n";
echo "OpenAI API Key: " . (env('OPENAI_API_KEY') ? 'Configurada ✓' : 'NO CONFIGURADA ❌') . "\n";
echo "Google API Key: " . (env('GOOGLE_API_KEY') ? 'Configurada ✓' : 'NO CONFIGURADA ❌') . "\n";
echo "AI Assistant Embeddings: " . (env('AI_ASSISTANT_USE_EMBEDDINGS', false) ? 'Activado' : 'Desactivado') . "\n";

// 2. Verificar documentos existentes
echo "\n📄 DOCUMENTOS EXISTENTES\n";
echo str_repeat("-", 40) . "\n";
$documents = AiDocument::select('id', 'name', 'processing_status', 'processing_step', 'processing_error', 'created_at')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

if ($documents->count() > 0) {
    foreach ($documents as $doc) {
        echo "ID: {$doc->id} | {$doc->name}\n";
        echo "  Estado: {$doc->processing_status} | Paso: {$doc->processing_step}\n";
        if ($doc->processing_error) {
            echo "  Error: {$doc->processing_error}\n";
        }
        echo "  Creado: {$doc->created_at}\n\n";
    }
} else {
    echo "No hay documentos en la base de datos.\n";
}

// 3. Verificar servicios
echo "\n🔨 VERIFICANDO SERVICIOS\n";
echo str_repeat("-", 40) . "\n";

try {
    $extractorService = app(ExtractorService::class);
    echo "ExtractorService: ✓ Disponible\n";
} catch (Exception $e) {
    echo "ExtractorService: ❌ Error - " . $e->getMessage() . "\n";
}

try {
    $chunkerService = app(ChunkerService::class);
    echo "ChunkerService: ✓ Disponible\n";
} catch (Exception $e) {
    echo "ChunkerService: ❌ Error - " . $e->getMessage() . "\n";
}

try {
    $driveService = app(GoogleDriveService::class);
    echo "GoogleDriveService: ✓ Disponible\n";
} catch (Exception $e) {
    echo "GoogleDriveService: ❌ Error - " . $e->getMessage() . "\n";
}

// 4. Verificar permisos de archivos
echo "\n📁 VERIFICANDO PERMISOS\n";
echo str_repeat("-", 40) . "\n";
$tempDir = sys_get_temp_dir();
echo "Directorio temporal: {$tempDir}\n";
echo "Escribible: " . (is_writable($tempDir) ? '✓' : '❌') . "\n";

$storageDir = storage_path();
echo "Directorio storage: {$storageDir}\n";
echo "Escribible: " . (is_writable($storageDir) ? '✓' : '❌') . "\n";

// 5. Verificar binarios OCR
echo "\n🔍 VERIFICANDO BINARIOS OCR\n";
echo str_repeat("-", 40) . "\n";

$tesseractPath = env('TESSERACT_PATH', 'tesseract');
$pdfToTextPath = env('PDFTOTEXT_PATH', 'pdftotext');
$pdftoppmPath = env('PDFTOPPM_PATH', 'pdftoppm');

// En Windows, verificar si están en el PATH
if (PHP_OS_FAMILY === 'Windows') {
    $tesseractExists = shell_exec('where tesseract 2>nul') !== null;
    $pdfToTextExists = shell_exec('where pdftotext 2>nul') !== null;

    echo "Tesseract: " . ($tesseractExists ? '✓ Disponible' : '❌ No encontrado') . "\n";
    echo "PDFtoText: " . ($pdfToTextExists ? '✓ Disponible' : '❌ No encontrado') . "\n";
} else {
    echo "Tesseract Path: {$tesseractPath}\n";
    echo "PDFtoText Path: {$pdfToTextPath}\n";
    echo "Pdftoppm Path: {$pdftoppmPath}\n";
}

// 6. Test de procesamiento si hay un documento
echo "\n🧪 TEST DE PROCESAMIENTO\n";
echo str_repeat("-", 40) . "\n";

$testDoc = AiDocument::where('processing_status', 'completed')
    ->whereNotNull('extracted_text')
    ->first();

if ($testDoc) {
    echo "Documento de prueba: {$testDoc->name} (ID: {$testDoc->id})\n";
    echo "Texto extraído: " . (strlen($testDoc->extracted_text) > 0 ? '✓ Presente' : '❌ Vacío') . "\n";
    echo "Caracteres: " . strlen($testDoc->extracted_text) . "\n";
    echo "Muestra: " . substr($testDoc->extracted_text, 0, 200) . "...\n";

    // Verificar si está en embeddings
    $embeddingCount = DB::table('ai_context_embeddings')
        ->where('content_type', 'document')
        ->where('content_id', $testDoc->id)
        ->count();

    echo "Embeddings generados: {$embeddingCount}\n";
} else {
    echo "No hay documentos completados para probar.\n";
}

// 7. Verificar configuración de API endpoints
echo "\n🌐 VERIFICANDO ENDPOINTS API\n";
echo str_repeat("-", 40) . "\n";

// Simular una consulta al asistente con documentos
$user = User::first();
if ($user) {
    echo "Usuario de prueba: {$user->username}\n";

    // Verificar sesiones de chat
    $sessionCount = DB::table('ai_chat_sessions')
        ->where('username', $user->username)
        ->count();

    echo "Sesiones de chat: {$sessionCount}\n";

    // Verificar documentos del usuario
    $userDocCount = AiDocument::where('username', $user->username)->count();
    echo "Documentos del usuario: {$userDocCount}\n";
}

// 8. Recomendaciones
echo "\n💡 DIAGNÓSTICO Y RECOMENDACIONES\n";
echo str_repeat("=", 50) . "\n";

$issues = [];
$recommendations = [];

if (config('queue.default') === 'sync') {
    $issues[] = "Queue configurado como 'sync' puede causar timeouts";
    $recommendations[] = "Cambiar QUEUE_CONNECTION a 'database' o 'redis' en .env";
}

if (!env('OPENAI_API_KEY')) {
    $issues[] = "OpenAI API Key no configurada";
    $recommendations[] = "Configurar OPENAI_API_KEY en .env";
}

if (PHP_OS_FAMILY === 'Windows') {
    $tesseractExists = shell_exec('where tesseract 2>nul') !== null;
    $pdfToTextExists = shell_exec('where pdftotext 2>nul') !== null;

    if (!$tesseractExists) {
        $issues[] = "Tesseract no está disponible (necesario para OCR de imágenes)";
        $recommendations[] = "Instalar Tesseract OCR: choco install tesseract";
    }

    if (!$pdfToTextExists) {
        $issues[] = "PDFtoText no está disponible (necesario para extraer texto de PDFs)";
        $recommendations[] = "Instalar Poppler utils: choco install poppler";
    }
}

if (count($issues) > 0) {
    echo "❌ PROBLEMAS ENCONTRADOS:\n";
    foreach ($issues as $i => $issue) {
        echo "  " . ($i + 1) . ". {$issue}\n";
    }

    echo "\n✅ RECOMENDACIONES:\n";
    foreach ($recommendations as $i => $rec) {
        echo "  " . ($i + 1) . ". {$rec}\n";
    }
} else {
    echo "✅ No se encontraron problemas evidentes en la configuración.\n";
    echo "El sistema de documentos debería estar funcionando correctamente.\n";
}

// 9. Script de reparación
echo "\n🔧 ¿EJECUTAR REPARACIONES AUTOMÁTICAS? (y/n): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
    echo "\n🛠️  EJECUTANDO REPARACIONES...\n";

    // Reprocesar documentos fallidos
    $failedDocs = AiDocument::where('processing_status', 'failed')
        ->orWhere('processing_status', 'pending')
        ->get();

    if ($failedDocs->count() > 0) {
        echo "Reprocesando {$failedDocs->count()} documento(s) fallido(s)...\n";
        foreach ($failedDocs as $doc) {
            $doc->update([
                'processing_status' => 'pending',
                'processing_error' => null,
                'processing_progress' => 0
            ]);

            ProcessAiDocumentJob::dispatch($doc->id);
            echo "  - Documento {$doc->id} ({$doc->name}) en cola\n";
        }
    }

    echo "✅ Reparaciones completadas.\n";
}

echo "\n🎉 Diagnóstico completo.\n";
