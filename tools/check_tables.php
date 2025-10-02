<?php
// Checks if specific tables exist in the current Laravel DB connection

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$tables = [
    'transcriptions',
    'transcriptions_laravel',
];

echo "=== TABLE EXISTENCE CHECK ===\n";
echo "Connection: " . Config::get('database.default') . "\n";

try {
    $driver = Config::get('database.connections.' . Config::get('database.default') . '.driver');

    foreach ($tables as $table) {
        $exists = false;
        if ($driver === 'mysql') {
            $db = Config::get('database.connections.' . Config::get('database.default') . '.database');
            $row = DB::selectOne(
                'SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
                [$db, $table]
            );
            $exists = (int)($row->cnt ?? 0) > 0;
        } else {
            // Fallback generic check
            try {
                DB::table($table)->limit(1)->get();
                $exists = true;
            } catch (Throwable $e) {
                $exists = false;
            }
        }
        echo sprintf("- %s: %s\n", $table, $exists ? 'EXISTS' : 'MISSING');
    }
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}

