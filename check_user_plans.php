<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== ROLES Y PLAN_CODES ACTUALES ===" . PHP_EOL;

$roles = User::distinct()->pluck('roles')->filter()->values();
$planCodes = User::distinct()->pluck('plan_code')->filter()->values();

echo "Roles únicos:" . PHP_EOL;
foreach($roles as $role) {
    $count = User::where('roles', $role)->count();
    echo "- {$role}: {$count} usuarios" . PHP_EOL;
}

echo PHP_EOL . "Plan codes únicos:" . PHP_EOL;
foreach($planCodes as $plan) {
    $count = User::where('plan_code', $plan)->count();
    echo "- {$plan}: {$count} usuarios" . PHP_EOL;
}

echo PHP_EOL . "Usuarios con plan_expires_at:" . PHP_EOL;
$withExpiry = User::whereNotNull('plan_expires_at')->count();
$expired = User::where('plan_expires_at', '<', now())->count();
$active = User::where('plan_expires_at', '>=', now())->count();

echo "- Con fecha de expiración: {$withExpiry}" . PHP_EOL;
echo "- Expirados: {$expired}" . PHP_EOL;
echo "- Activos: {$active}" . PHP_EOL;
