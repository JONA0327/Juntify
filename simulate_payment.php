<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Plan;
use App\Services\UserPlans\UserPlanService;
use App\Models\MercadoPagoPayment;

try {
    echo "=== SIMULADOR DE PAGO EXITOSO ===\n\n";

    // 1. Buscar un usuario free para hacer la prueba
    $freeUser = DB::table('users')->where('roles', 'free')->first();

    if (!$freeUser) {
        echo "❌ No se encontró ningún usuario con rol 'free'\n";
        exit(1);
    }

    echo "👤 Usuario de prueba: {$freeUser->email} (ID: {$freeUser->id})\n";

    // 2. Buscar un plan básico
    $basicPlan = DB::table('plans')->where('code', 'basico')->first();

    if (!$basicPlan) {
        echo "❌ No se encontró el plan 'basico'\n";
        exit(1);
    }

    echo "📦 Plan: {$basicPlan->name} - \${$basicPlan->price}\n\n";

    // 3. Simular un pago exitoso
    $externalReference = 'test_' . time();
    $paymentId = 'test_payment_' . rand(1000000, 9999999);

    echo "💳 Simulando pago exitoso...\n";
    echo "External Reference: {$externalReference}\n";
    echo "Payment ID: {$paymentId}\n\n";

    // 4. Crear el registro de pago en MercadoPago
    $mercadoPayment = MercadoPagoPayment::create([
        'external_reference' => $externalReference,
        'preference_id' => 'pref_test_' . rand(1000, 9999),
        'payment_id' => $paymentId,
        'item_type' => 'plan',
        'item_id' => $basicPlan->code,
        'item_name' => $basicPlan->name,
        'amount' => (float) $basicPlan->price,
        'currency' => 'MXN',
        'status' => 'approved',
        'metadata' => [
            'user_id' => $freeUser->id,
            'plan_id' => $basicPlan->id,
            'plan_code' => 'basic', // Usar 'basic' consistente con el rol
            'role' => 'basic'
        ]
    ]);

    echo "✅ Pago registrado en mercado_pago_payments (ID: {$mercadoPayment->id})\n";

    // 5. Ejecutar el servicio UserPlanService
    echo "\n🔄 Ejecutando UserPlanService...\n";

    $user = User::find($freeUser->id);
    $metadata = [
        'user_id' => $freeUser->id,
        'plan_id' => $basicPlan->id,
        'plan_code' => 'basic', // Usar 'basic' en lugar del código de la DB
        'role' => 'basic'
    ];

    $purchaseData = [
        'provider' => 'mercado_pago',
        'payment_id' => $paymentId,
        'external_reference' => $externalReference,
        'status' => 'approved',
        'amount' => (float) $basicPlan->price,
        'currency' => 'MXN',
        'paid_at' => now()->toIso8601String(),
        'metadata' => $metadata,
    ];

    $userPlanService = app(\App\Services\UserPlans\UserPlanService::class);
    $result = $userPlanService->activateFromPayment($user, $metadata, $purchaseData);

    echo "✅ UserPlanService ejecutado\n";

    // 6. Verificar el resultado
    echo "\n📊 VERIFICANDO RESULTADOS...\n";

    $updatedUser = User::find($freeUser->id);
    echo "Rol anterior: {$freeUser->roles}\n";
    echo "Rol actual: {$updatedUser->roles}\n";
    echo "Plan anterior: {$freeUser->plan}\n";
    echo "Plan actual: {$updatedUser->plan}\n";
    echo "Plan code anterior: {$freeUser->plan_code}\n";
    echo "Plan code actual: {$updatedUser->plan_code}\n";

    // 7. Verificar user_plans
    $userPlan = DB::table('user_plans')->where('user_id', $freeUser->id)->orderBy('created_at', 'desc')->first();
    if ($userPlan) {
        echo "\n✅ user_plans creado:\n";
        echo "- Plan ID: {$userPlan->plan_id}\n";
        echo "- Role: {$userPlan->role}\n";
        echo "- Status: {$userPlan->status}\n";
        echo "- Starts: {$userPlan->starts_at}\n";
        echo "- Expires: {$userPlan->expires_at}\n";
    } else {
        echo "\n❌ No se creó registro en user_plans\n";
    }

    // 8. Verificar plan_purchases
    $planPurchase = DB::table('plan_purchases')->where('user_id', $freeUser->id)->orderBy('created_at', 'desc')->first();
    if ($planPurchase) {
        echo "\n✅ plan_purchases creado:\n";
        echo "- Payment ID: {$planPurchase->payment_id}\n";
        echo "- Status: {$planPurchase->status}\n";
        echo "- Amount: {$planPurchase->amount}\n";
    } else {
        echo "\n❌ No se creó registro en plan_purchases\n";
    }

    echo "\n🎉 SIMULACIÓN COMPLETA\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
