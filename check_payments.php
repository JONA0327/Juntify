<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Plan;
use App\Models\MercadoPagoPayment;
use App\Models\PlanPurchase;

echo "=== VERIFICACIÓN DE PAGOS Y ACTUALIZACIONES ===" . PHP_EOL;

// Verificar pagos aprobados en MercadoPago
$approvedPayments = MercadoPagoPayment::where('status', 'approved')->get();
echo "Pagos aprobados en MercadoPago: " . $approvedPayments->count() . PHP_EOL;

// Verificar plan purchases
$planPurchases = PlanPurchase::where('status', 'approved')->get();
echo "Plan purchases aprobados: " . $planPurchases->count() . PHP_EOL;

// Verificar usuarios que deberían tener planes pero tienen free
$usersWithFreeRole = User::where('roles', 'free')->count();
echo "Usuarios con rol free: " . $usersWithFreeRole . PHP_EOL;

// Ver algunos ejemplos de pagos aprobados y sus usuarios
if ($approvedPayments->count() > 0) {
    echo PHP_EOL . "=== EJEMPLOS DE PAGOS APROBADOS ===" . PHP_EOL;
    foreach ($approvedPayments->take(5) as $payment) {
        $metadata = $payment->metadata ?? [];
        $userId = $metadata['user_id'] ?? null;
        $planCode = $metadata['plan_code'] ?? 'desconocido';
        $role = $metadata['role'] ?? 'no definido';

        if ($userId) {
            $user = User::find($userId);
            $currentRole = $user ? $user->roles : 'usuario no encontrado';
            echo "- Pago {$payment->id}: plan_code={$planCode}, role_metadata={$role}, usuario_rol_actual={$currentRole}" . PHP_EOL;
        }
    }
}

// Ver si hay planes definidos en la tabla plans
$plans = Plan::all();
echo PHP_EOL . "=== PLANES DISPONIBLES ===" . PHP_EOL;
foreach ($plans as $plan) {
    echo "- {$plan->code}: {$plan->name} (\${$plan->price})" . PHP_EOL;
}
