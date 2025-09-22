<?php
// Quick diagnostic to test PDF extraction (including OCR) end-to-end.
// Usage: php tools/test_extract_pdf.php /absolute/path/to/file.pdf

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Run this script from CLI.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/test_extract_pdf.php /path/to/file.pdf\n");
    exit(1);
}

$filePath = $argv[1];
if (!is_file($filePath)) {
    fwrite(STDERR, "File not found: {$filePath}\n");
    exit(1);
}

// Bootstrap Laravel so facades/config/env work
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\ExtractorService;

$mimeType = 'application/pdf';
$filename = basename($filePath);

$service = app(ExtractorService::class);

try {
    $result = $service->extract($filePath, $mimeType, $filename);
    $text = $result['text'] ?? '';
    $metadata = $result['metadata'] ?? [];

    echo "=== Extraction OK ===\n";
    echo "Engine: " . ($metadata['engine'] ?? 'unknown') . "\n";
    echo "Detected Type: " . ($metadata['detected_type'] ?? 'unknown') . "\n";
    if (isset($metadata['pages'])) {
        echo "Pages: {$metadata['pages']}\n";
    }

    $preview = mb_substr($text, 0, 800);
    echo "\n--- Text preview (first 800 chars) ---\n";
    echo $preview . "\n";
    echo "\nLength: " . mb_strlen($text) . " chars\n";
} catch (Throwable $e) {
    echo "Extraction FAILED: " . $e->getMessage() . "\n";
}
