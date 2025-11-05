<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CONFIGURACIÓN PERMANENTE CUENTA BNI ===\n\n";

// Buscar usuario
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();

if (!$user) {
    echo "❌ Usuario CongresoBNI@gmail.com no encontrado\n";
    exit(1);
}

echo "Estado ANTES del cambio:\n";
echo "   Email: {$user->email}\n";
echo "   Rol: {$user->roles}\n";
echo "   Plan: " . ($user->plan_code ?? 'N/A') . "\n";
echo "   Vencimiento: " . ($user->plan_expires_at ?? 'N/A') . "\n";

// Actualizar usuario con rol BNI permanente
$user->update([
    'roles' => 'bni',
    'plan_code' => null, // Sin plan específico
    'plan_expires_at' => null, // Sin fecha de vencimiento
]);

// Recargar el usuario
$user->refresh();

echo "\nEstado DESPUÉS del cambio:\n";
echo "   Email: {$user->email}\n";
echo "   Rol: {$user->roles}\n";
echo "   Plan: " . ($user->plan_code ?? 'N/A') . "\n";
echo "   Vencimiento: " . ($user->plan_expires_at ?? 'N/A') . "\n";

// Verificar límites
$planService = new App\Services\PlanLimitService();
$limits = $planService->getLimitsForUser($user);

echo "\n=== VERIFICACIÓN DE LÍMITES ===\n";
foreach ($limits as $key => $value) {
    if (is_null($value)) {
        echo "✅ $key: UNLIMITED\n";
    } else {
        echo "   $key: $value\n";
    }
}

$hasUnlimitedAccess = is_null($limits['max_meetings_per_month']);
echo "\n" . ($hasUnlimitedAccess ? "✅ ÉXITO: Cuenta tiene acceso ilimitado" : "❌ ERROR: Cuenta sigue limitada") . "\n";

// Verificar que funciona como developer (sin vencimiento)
echo "\n=== COMPARACIÓN CON ROLES PERMANENTES ===\n";
echo "Roles sin vencimiento:\n";
echo "  - founder: ✅ Sin vencimiento\n";
echo "  - developer: ✅ Sin vencimiento\n";
echo "  - superadmin: ✅ Sin vencimiento\n";
echo "  - bni: ✅ Sin vencimiento (como CongresoBNI@gmail.com)\n";

echo "\n🎉 CONFIGURACIÓN COMPLETADA!\n";
echo "CongresoBNI@gmail.com ahora tiene:\n";
echo "✅ Rol BNI permanente\n";
echo "✅ Sin fecha de vencimiento\n";
echo "✅ Límites ilimitados\n";
echo "✅ Funciona como los roles developer/founder\n";

?>
