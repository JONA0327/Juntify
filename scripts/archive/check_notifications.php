<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "=== NOTIFICATIONS TABLE FULL STRUCTURE ===\n";
    $notifColumns = \Illuminate\Support\Facades\DB::select('DESCRIBE notifications');
    foreach ($notifColumns as $column) {
        echo "notifications.{$column->Field}: {$column->Type} - {$column->Null} - {$column->Key} - {$column->Default} - {$column->Extra}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
