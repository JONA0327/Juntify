<?php
// Simple DB connectivity test that boots Laravel and checks the default connection.

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

function bytes_to_human($val) {
    $val = trim(strval($val));
    $last = strtolower(substr($val, -1));
    $num = (float)$val;
    switch ($last) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default: return (float)$val;
    }
}

try {
    $connName = Config::get('database.default');
    $config = Config::get("database.connections.$connName");
    echo "=== DB CONNECTION TEST ===\n";
    echo "Connection: $connName\n";
    if (is_array($config)) {
        $safe = $config;
        unset($safe['password']);
        echo "Driver: ".$safe['driver']."\n";
        echo "Host: ".$safe['host']."\n";
        echo "Port: ".$safe['port']."\n";
        echo "Database: ".$safe['database']."\n";
        echo "Username: ".$safe['username']."\n";
    }

    $pdo = DB::connection()->getPdo();
    echo "PDO driver: ".$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."\n";

    // Quick query depending on driver
    $driver = $config['driver'] ?? '';
    if ($driver === 'mysql') {
        $row = DB::selectOne('SELECT 1 as ok, VERSION() as version');
        echo "Test query: ok=".$row->ok.", version=".$row->version."\n";
    } elseif ($driver === 'pgsql') {
        $row = DB::selectOne('SELECT 1 as ok, version() as version');
        echo "Test query: ok=".$row->ok.", version=".$row->version."\n";
    } else {
        $row = DB::selectOne('SELECT 1 as ok');
        echo "Test query: ok=".$row->ok."\n";
    }

    echo "Status: PASS\n";

    // Also print PHP upload limits as they sometimes impact API payloads
    echo "\nPHP limits (for reference):\n";
    echo "post_max_size=".ini_get('post_max_size')." (".bytes_to_human(ini_get('post_max_size'))." bytes)\n";
    echo "upload_max_filesize=".ini_get('upload_max_filesize')." (".bytes_to_human(ini_get('upload_max_filesize'))." bytes)\n";
    echo "memory_limit=".ini_get('memory_limit')."\n";

} catch (Throwable $e) {
    echo "Status: FAIL\n";
    echo "Error: ".$e->getMessage()."\n";
    echo "Trace:\n".$e->getTraceAsString()."\n";
    exit(1);
}
