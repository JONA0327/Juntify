<?php
// Usage: php tools/inspect_table.php notifications

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$table = $argv[1] ?? null;
if (!$table) {
    fwrite(STDERR, "Please provide a table name.\n");
    exit(1);
}

try {
    $rows = Illuminate\Support\Facades\DB::select('DESCRIBE ' . $table);
    echo "=== $table structure ===\n";
    foreach ($rows as $r) {
        $default = $r->Default === null ? 'NULL' : $r->Default;
        echo sprintf("%-24s | %-20s | %-3s | %-3s | %-10s | %s\n", $r->Field, $r->Type, $r->Null, $r->Key, $default, $r->Extra);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
