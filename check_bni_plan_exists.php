<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN DE PLANES EN BD ===\n\n";

// Verificar qué planes existen
$plans = App\Models\Plan::all();

echo "📋 Planes existentes en la base de datos:\n";
foreach ($plans as $plan) {
    echo "   - ID: {$plan->id}\n";
    echo "     Código: {$plan->code}\n";
    echo "     Nombre: {$plan->name}\n";
    echo "     Activo: " . ($plan->is_active ? "Sí" : "No") . "\n\n";
}

echo "🔍 Buscando plan con code 'BNI':\n";
$bniPlan = App\Models\Plan::where('code', 'BNI')->first();
if ($bniPlan) {
    echo "   ✅ Plan BNI encontrado: {$bniPlan->name}\n";
} else {
    echo "   ❌ Plan BNI NO encontrado\n";
    echo "   ℹ️  Necesitamos crear un plan BNI en la base de datos\n";
}

echo "\n🎯 Verificación del usuario BNI:\n";
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();
echo "   - plan field: " . ($user->plan ?? 'NULL') . "\n";
echo "   - roles field: " . ($user->roles ?? 'NULL') . "\n";

echo "\n💡 Solución:\n";
echo "   1. Crear un Plan BNI en la base de datos, O\n";
echo "   2. Modificar la vista para manejar el caso especial BNI\n";
