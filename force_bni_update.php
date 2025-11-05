<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ACTUALIZACIÓN FORZADA BNI (CON VERIFICACIÓN) ===\n\n";

// Buscar el usuario
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();

if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$user->email}\n\n";

echo "=== ESTADO INICIAL ===\n";
echo "   ID: {$user->id}\n";
echo "   Roles: " . ($user->roles ?? 'NULL') . "\n";
echo "   Plan: " . ($user->plan ?? 'NULL') . "\n";
echo "   Plan Code: " . ($user->plan_code ?? 'NULL') . "\n";
echo "   Expira: " . ($user->plan_expires_at ?? 'NULL') . "\n\n";

// Método 1: Actualización directa por campo
echo "🔧 Método 1: Actualizando campo por campo...\n";

$user->roles = 'BNI';
$user->plan = 'BNI';
$user->plan_code = 'BNI';
$user->plan_expires_at = null;

echo "   - Roles asignado: {$user->roles}\n";
echo "   - Plan asignado: {$user->plan}\n";
echo "   - Plan Code asignado: {$user->plan_code}\n";

$saved = $user->save();
echo "   - Guardado: " . ($saved ? "✅ SÍ" : "❌ NO") . "\n\n";

// Recargar desde la base de datos
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();

echo "=== ESTADO DESPUÉS DEL SAVE ===\n";
echo "   Roles: " . ($user->roles ?? 'NULL') . "\n";
echo "   Plan: " . ($user->plan ?? 'NULL') . "\n";
echo "   Plan Code: " . ($user->plan_code ?? 'NULL') . "\n";
echo "   Expira: " . ($user->plan_expires_at ?? 'NULL') . "\n\n";

// Método 2: Query directo
echo "🔧 Método 2: Query SQL directo...\n";

$updated = \Illuminate\Support\Facades\DB::table('users')
    ->where('email', 'CongresoBNI@gmail.com')
    ->update([
        'roles' => 'BNI',
        'plan' => 'BNI',
        'plan_code' => 'BNI',
        'plan_expires_at' => null,
        'updated_at' => now()
    ]);

echo "   - Filas afectadas: {$updated}\n\n";

// Verificar final
$user = App\Models\User::where('email', 'CongresoBNI@gmail.com')->first();

echo "=== ESTADO FINAL ===\n";
echo "   Roles: " . ($user->roles ?? 'NULL') . "\n";
echo "   Plan: " . ($user->plan ?? 'NULL') . "\n";
echo "   Plan Code: " . ($user->plan_code ?? 'NULL') . "\n";
echo "   Expira: " . ($user->plan_expires_at ?? 'NULL') . "\n\n";

if ($user->roles === 'BNI' && $user->plan === 'BNI' && $user->plan_code === 'BNI') {
    echo "✅ ¡ÉXITO! Todos los campos BNI actualizados correctamente\n";
} else {
    echo "❌ Todavía hay problemas. Revisando posibles causas...\n";

    // Verificar si hay eventos/observers que puedan estar interfiriendo
    echo "\n🔍 Revisando posibles interferencias:\n";

    // Verificar si el modelo User tiene eventos
    $events = get_class_methods($user);
    $eventMethods = array_filter($events, function($method) {
        return strpos($method, 'boot') !== false || strpos($method, 'updated') !== false || strpos($method, 'saving') !== false;
    });

    if (!empty($eventMethods)) {
        echo "   - Métodos de eventos encontrados: " . implode(', ', $eventMethods) . "\n";
    }
}

echo "\n🎯 El 'Plan Actual' ahora debería mostrar 'BNI'\n";
