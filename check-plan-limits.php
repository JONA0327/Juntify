<?php
require_once(__DIR__ . '/bootstrap/app.php');

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

// Bootstrap Laravel
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$limits = DB::table('plan_limits')->get();

echo "\n=== Plan Limits ===\n\n";
foreach ($limits as $limit) {
    echo "Role: " . ($limit->role ?? 'N/A') . "\n";
    echo "  - Allow Postpone: " . ($limit->allow_postpone ?? 'N/A') . "\n";
    echo "  - Max Meetings/Month: " . ($limit->max_meetings_per_month ?? 'unlimited') . "\n";
    echo "  - Max Duration Minutes: " . ($limit->max_duration_minutes ?? 'N/A') . "\n";
    echo "\n";
}

echo "\n=== User jona03278 ===\n\n";
$user = DB::table('users')->where('email', 'jona03278@gmail.com')->first();
if ($user) {
    echo "User found\n";
    echo "  - Username: " . ($user->username ?? 'N/A') . "\n";
    echo "  - Roles: " . ($user->roles ?? 'N/A') . "\n";
    echo "  - Plan: " . ($user->plan ?? 'N/A') . "\n";
    echo "  - Plan Code: " . ($user->plan_code ?? 'N/A') . "\n";
} else {
    echo "User not found\n";
}
?>
