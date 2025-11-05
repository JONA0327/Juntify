<?php

require __DIR__.'/bootstrap/app.php';

$app = \Illuminate\Foundation\Application::getInstance();
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "=== ACTUALIZANDO PLAN BNI COMPLETO ===\n\n";

// Buscar el usuario
$user = User::where('email', 'CongresoBNI@gmail.com')->first();

if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado:\n";
echo "   Email: {$user->email}\n";
echo "   ID: {$user->id}\n\n";

echo "=== ESTADO ANTES ===\n";
echo "   Roles: " . ($user->roles ?? 'NULL') . "\n";
echo "   Plan: " . ($user->plan ?? 'NULL') . "\n";
echo "   Plan Code: " . ($user->plan_code ?? 'NULL') . "\n";
echo "   Expira: " . ($user->plan_expires_at ?? 'NULL') . "\n\n";

echo "🔧 Actualizando todos los campos BNI...\n";

// Actualizar TODOS los campos relacionados con BNI
$user->update([
    'roles' => 'BNI',           // Rol BNI
    'plan' => 'BNI',            // Plan BNI (esto es lo que se mostrará en "Plan Actual")
    'plan_code' => 'BNI',       // Código BNI
    'plan_expires_at' => null   // Sin expiración
]);

// Recargar el usuario para verificar
$user = $user->fresh();

echo "\n=== ESTADO DESPUÉS ===\n";
echo "   Roles: " . ($user->roles ?? 'NULL') . "\n";
echo "   Plan: " . ($user->plan ?? 'NULL') . "\n";
echo "   Plan Code: " . ($user->plan_code ?? 'NULL') . "\n";
echo "   Expira: " . ($user->plan_expires_at ?? 'NULL') . "\n\n";

echo "✅ ¡Actualización completa!\n";
echo "Ahora el 'Plan Actual' debería mostrar 'BNI' en lugar de 'Free'\n";
