<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$logFile = 'storage/logs/laravel.log';
$lines = file($logFile);
$relevantLines = [];

foreach ($lines as $line) {
    if (strpos($line, 'grantDriveAccessForShare') !== false ||
        strpos($line, 'shareDriveItemWithFallback') !== false ||
        strpos($line, 'Token refresh') !== false ||
        strpos($line, 'shareItem via') !== false ||
        strpos($line, 'Service Account') !== false) {
        $relevantLines[] = trim($line);
    }
}

echo "=== Extended Drive Permission Granting Logs (Last 30) ===\n";
foreach (array_slice($relevantLines, -30) as $line) {
    echo $line . "\n";
}
