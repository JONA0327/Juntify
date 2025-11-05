<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN Y CORRECCIÓN CUENTA BNI ===\n\n";

// Buscar usuario
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();

if (!$user) {
    echo "❌ Usuario CongresoBNI@gmail.com no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado:\n";
echo "   Email: {$user->email}\n";
echo "   Nombre: {$user->name}\n";
echo "   ID: {$user->id}\n";
echo "   Rol actual: {$user->roles}\n";
echo "   Plan actual: " . ($user->plan_code ?? 'N/A') . "\n";
echo "   Fecha vencimiento: " . ($user->plan_expires_at ?? 'N/A') . "\n";
echo "   Creado: {$user->created_at}\n";

// Verificar si es un rol ilimitado
$planService = new App\Services\PlanLimitService();

echo "\n=== ANÁLISIS ACTUAL ===\n";
$limits = $planService->getLimitsForUser($user);
echo "Límites actuales:\n";
foreach ($limits as $key => $value) {
    if (is_null($value)) {
        echo "  $key: UNLIMITED\n";
    } else {
        echo "  $key: $value\n";
    }
}

// Verificar si tiene acceso ilimitado
$hasUnlimitedAccess = is_null($limits['max_meetings_per_month']);
echo "\nTiene acceso ilimitado: " . ($hasUnlimitedAccess ? "✅ SÍ" : "❌ NO") . "\n";

echo "\n=== ROLES ILIMITADOS ACTUALES ===\n";
$unlimitedRoles = ['founder', 'developer', 'superadmin', 'bni'];
foreach ($unlimitedRoles as $role) {
    $isUnlimited = in_array(strtolower($role), ['founder', 'developer', 'superadmin', 'bni']);
    echo "  $role: " . ($isUnlimited ? "✅ ILIMITADO" : "❌ LIMITADO") . "\n";
}

?>
