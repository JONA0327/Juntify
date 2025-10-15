<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Plan;

echo "=== PLANES ACTUALIZADOS ===\n\n";

$plans = Plan::select('name', 'features')->whereIn('code', ['freemium', 'basico'])->get();

foreach($plans as $plan) {
    echo "📋 {$plan->name}:\n";
    foreach($plan->features as $feature) {
        echo "   ✓ {$feature}\n";
    }
    echo "\n";
}

// También verificar los límites
echo "=== LÍMITES DE REUNIONES ===\n\n";

$limits = \DB::table('plan_limits')->select('role', 'max_meetings_per_month')->whereIn('role', ['free', 'basic'])->get();

foreach($limits as $limit) {
    echo "🔒 {$limit->role}: {$limit->max_meetings_per_month} reuniones por mes\n";
}
