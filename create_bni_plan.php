<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CREANDO PLAN BNI EN BASE DE DATOS ===\n\n";

// Verificar si ya existe
$existingPlan = App\Models\Plan::where('code', 'BNI')->first();
if ($existingPlan) {
    echo "✅ Plan BNI ya existe:\n";
    echo "   - ID: {$existingPlan->id}\n";
    echo "   - Código: {$existingPlan->code}\n";
    echo "   - Nombre: {$existingPlan->name}\n";
    echo "   - Precio: $" . number_format($existingPlan->price, 2) . "\n";
    echo "   - Activo: " . ($existingPlan->is_active ? "Sí" : "No") . "\n";
} else {
    echo "🔧 Creando plan BNI...\n";

    $bniPlan = App\Models\Plan::create([
        'code' => 'BNI',
        'name' => 'BNI',
        'description' => 'Plan especial para miembros de BNI - Acceso ilimitado con características premium',
        'price' => 0.00, // Gratis para BNI
        'monthly_meetings' => -1, // -1 = ilimitado
        'max_duration' => 240, // 4 horas
        'max_participants' => 50,
        'storage_gb' => 100,
        'features' => json_encode([
            'unlimited_meetings' => true,
            'temp_storage' => true,
            'unencrypted_files' => true,
            'auto_download' => true,
            'premium_support' => true,
            'custom_branding' => false
        ]),
        'is_active' => true,
        'sort_order' => 0 // Primer lugar
    ]);

    echo "✅ Plan BNI creado exitosamente:\n";
    echo "   - ID: {$bniPlan->id}\n";
    echo "   - Código: {$bniPlan->code}\n";
    echo "   - Nombre: {$bniPlan->name}\n";
    echo "   - Precio: $" . number_format($bniPlan->price, 2) . "\n";
    echo "   - Reuniones mensuales: " . ($bniPlan->monthly_meetings == -1 ? 'Ilimitadas' : $bniPlan->monthly_meetings) . "\n";
    echo "   - Duración máxima: {$bniPlan->max_duration} minutos\n";
    echo "   - Activo: " . ($bniPlan->is_active ? "Sí" : "No") . "\n";
}

echo "\n🎯 Verificando vista ahora...\n";

// Simular el código de la vista
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();
$planName = 'Free';
if ($user->plan && $user->plan !== 'free') {
    $plan = App\Models\Plan::where('code', $user->plan)->first();
    $planName = $plan ? $plan->name : ucfirst($user->plan);
}

echo "   - Campo 'plan' del usuario: {$user->plan}\n";
echo "   - Plan encontrado en BD: " . ($plan ?? 'No encontrado') . "\n";
echo "   - Nombre que se mostrará: {$planName}\n";

echo "\n✅ ¡Ahora el 'Tipo de plan' debería mostrar 'BNI' correctamente!\n";
