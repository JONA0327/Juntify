<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔧 VERIFICACIÓN RÁPIDA BNI\n";
echo "=========================\n\n";

// Usuario
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();

echo "👤 USUARIO:\n";
echo "   - Roles: {$user->roles}\n";
echo "   - Plan: {$user->plan}\n";
echo "   - Plan Code: {$user->plan_code}\n\n";

// Límites
echo "📊 LÍMITES:\n";
$limitsService = new App\Services\PlanLimitService();
$limits = $limitsService->getLimitsForUser($user);

echo "   - Max reuniones/mes: " . ($limits['max_meetings_per_month'] ?? 'NULL (Ilimitado)') . "\n";
echo "   - Usado este mes: " . $limits['used_this_month'] . "\n";
echo "   - Restante: " . ($limits['remaining'] ?? 'NULL (Ilimitado)') . "\n";
echo "   - Duración max: " . $limits['max_duration_minutes'] . " min\n\n";

// Check si es ilimitado
$isUnlimited = is_null($limits['max_meetings_per_month']) || is_null($limits['remaining']);
echo "🎯 ACCESO ILIMITADO: " . ($isUnlimited ? '✅ SÍ' : '❌ NO') . "\n\n";

// Plan en vista
echo "🎨 VISTA:\n";
$planName = 'Free';
if ($user->plan && $user->plan !== 'free') {
    $plan = App\Models\Plan::where('code', $user->plan)->first();
    $planName = $plan ? $plan->name : ucfirst($user->plan);
}
echo "   - Plan mostrado: '{$planName}'\n\n";

// Resumen final
echo "✅ ESTADO FINAL:\n";
echo "================\n";
echo "✅ Rol BNI: " . ($user->roles === 'BNI' ? 'CORRECTO' : 'ERROR') . "\n";
echo "✅ Plan BNI: " . ($user->plan === 'BNI' ? 'CORRECTO' : 'ERROR') . "\n";
echo "✅ Vista BNI: " . ($planName === 'BNI' ? 'CORRECTO' : 'ERROR') . "\n";
echo "✅ Acceso ilimitado: " . ($isUnlimited ? 'CORRECTO' : 'ERROR') . "\n";

$allGood = ($user->roles === 'BNI' && $user->plan === 'BNI' && $planName === 'BNI' && $isUnlimited);

echo "\n" . ($allGood ? "🎉 ¡TODO PERFECTO!" : "⚠️  Revisar issues") . "\n";
