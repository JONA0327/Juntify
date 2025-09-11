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
        strpos($line, 'shareItem via') !== false) {
        $relevantLines[] = trim($line);
    }
}

echo "=== Recent Drive Permission Granting Logs ===\n";
foreach (array_slice($relevantLines, -20) as $line) {
    echo $line . "\n";
}
