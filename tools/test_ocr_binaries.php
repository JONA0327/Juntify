<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ExtractorService;

// Minimal bootstrap if not running inside Laravel's HTTP kernel
$appPath = __DIR__ . '/../bootstrap/app.php';
if (file_exists($appPath)) {
    $app = require $appPath;
}

$service = new ExtractorService();

$reflect = new ReflectionClass($service);
$method = $reflect->getMethod('resolveBinary');
$method->setAccessible(true);

$binaries = ['tesseract','pdftotext','pdftoppm','gs'];

foreach ($binaries as $bin) {
    try {
        $path = $method->invoke($service, $bin);
        echo str_pad($bin, 12) . ': ' . ($path ?: '(not found)') . PHP_EOL;
    } catch (Throwable $e) {
        echo str_pad($bin, 12) . ': (error) ' . $e->getMessage() . PHP_EOL;
    }
}

