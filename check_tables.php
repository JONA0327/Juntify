<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "=== USERS TABLE STRUCTURE ===\n";
    $usersColumns = \Illuminate\Support\Facades\DB::select('DESCRIBE users');
    foreach ($usersColumns as $column) {
        if ($column->Field === 'id') {
            echo "users.id: {$column->Type} - {$column->Extra}\n";
        }
    }

    echo "\n=== MEETINGS TABLE STRUCTURE ===\n";
    $meetingsColumns = \Illuminate\Support\Facades\DB::select('DESCRIBE meetings');
    foreach ($meetingsColumns as $column) {
        if ($column->Field === 'id') {
            echo "meetings.id: {$column->Type} - {$column->Extra}\n";
        }
    }

    echo "\n=== CHECKING FOR shared_meetings TABLE ===\n";
    $tables = \Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE 'shared_meetings'");
    if (empty($tables)) {
        echo "shared_meetings table does not exist\n";
    } else {
        echo "shared_meetings table exists\n";
        $sharedColumns = \Illuminate\Support\Facades\DB::select('DESCRIBE shared_meetings');
        foreach ($sharedColumns as $column) {
            echo "shared_meetings.{$column->Field}: {$column->Type}\n";
        }
    }

    echo "\n=== CHECKING FOR notifications TABLE ===\n";
    $notifTables = \Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE 'notifications'");
    if (empty($notifTables)) {
        echo "notifications table does not exist\n";
    } else {
        echo "notifications table exists\n";
        $notifColumns = \Illuminate\Support\Facades\DB::select('DESCRIBE notifications');
        foreach ($notifColumns as $column) {
            if (in_array($column->Field, ['id', 'user_id', 'type'])) {
                echo "notifications.{$column->Field}: {$column->Type}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
