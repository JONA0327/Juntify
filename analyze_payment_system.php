<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== ANÁLISIS COMPLETO DEL SISTEMA DE PAGOS ===\n\n";

    // 1. Verificar pagos en MercadoPago
    $totalPayments = DB::table('mercado_pago_payments')->count();
    echo "1. PAGOS EN MERCADOPAGO\n";
    echo "Total de pagos registrados: $totalPayments\n";

    if ($totalPayments > 0) {
        echo "\nPagos por estado:\n";
        $paymentsByStatus = DB::table('mercado_pago_payments')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        foreach ($paymentsByStatus as $item) {
            echo "- {$item->status}: {$item->total}\n";
        }

        echo "\nÚltimos 5 pagos:\n";
        $recentPayments = DB::table('mercado_pago_payments')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'status', 'amount', 'metadata', 'created_at']);

        foreach ($recentPayments as $payment) {
            $metadata = json_decode($payment->metadata, true);
            $userId = $metadata['user_id'] ?? 'N/A';
            echo "- ID: {$payment->id}, Estado: {$payment->status}, Monto: {$payment->amount}, Usuario: {$userId}, Fecha: {$payment->created_at}\n";
        }
    }

    // 2. Verificar user_plans
    $totalUserPlans = DB::table('user_plans')->count();
    echo "\n2. USER PLANS\n";
    echo "Total de planes de usuario: $totalUserPlans\n";

    if ($totalUserPlans > 0) {
        echo "\nPlanes por estado:\n";
        $plansByStatus = DB::table('user_plans')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        foreach ($plansByStatus as $item) {
            echo "- {$item->status}: {$item->total}\n";
        }

        echo "\nPlanes por rol:\n";
        $plansByRole = DB::table('user_plans')
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->get();

        foreach ($plansByRole as $item) {
            $role = $item->role ?: 'NULL';
            echo "- {$role}: {$item->total}\n";
        }
    }

    // 3. Verificar plan_purchases
    $totalPurchases = DB::table('plan_purchases')->count();
    echo "\n3. PLAN PURCHASES\n";
    echo "Total de compras de planes: $totalPurchases\n";

    if ($totalPurchases > 0) {
        echo "\nCompras por estado:\n";
        $purchasesByStatus = DB::table('plan_purchases')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        foreach ($purchasesByStatus as $item) {
            echo "- {$item->status}: {$item->total}\n";
        }
    }

    // 4. Verificar distribución de usuarios actuales
    echo "\n4. DISTRIBUCIÓN DE USUARIOS ACTUALES (por columna 'roles')\n";
    $usersByRole = DB::table('users')
        ->select('roles', DB::raw('count(*) as total'))
        ->groupBy('roles')
        ->get();

    foreach ($usersByRole as $item) {
        $roles = $item->roles ?: 'NULL';
        echo "- {$roles}: {$item->total}\n";
    }

    echo "\n4b. DISTRIBUCIÓN POR PLAN\n";
    $usersByPlan = DB::table('users')
        ->select('plan', DB::raw('count(*) as total'))
        ->groupBy('plan')
        ->get();

    foreach ($usersByPlan as $item) {
        $plan = $item->plan ?: 'NULL';
        echo "- {$plan}: {$item->total}\n";
    }

    // 5. Verificar plan_code en usuarios
    echo "\n5. PLAN_CODE EN USUARIOS\n";
    $usersByPlanCode = DB::table('users')
        ->select('plan_code', DB::raw('count(*) as total'))
        ->groupBy('plan_code')
        ->get();

    foreach ($usersByPlanCode as $item) {
        $planCode = $item->plan_code ?: 'NULL';
        echo "- {$planCode}: {$item->total}\n";
    }

    // 6. Identificar posibles problemas
    echo "\n6. DIAGNÓSTICO DE PROBLEMAS\n";

    if ($totalPayments == 0) {
        echo "❌ No hay pagos registrados en mercado_pago_payments\n";
        echo "   Posible problema: Los webhooks no están funcionando\n";
    }

    if ($totalUserPlans == 0) {
        echo "❌ No hay planes de usuario registrados\n";
        echo "   Posible problema: UserPlanService no está creando registros\n";
    }

    if ($totalPurchases == 0) {
        echo "❌ No hay compras de planes registradas\n";
        echo "   Posible problema: PlanPurchase no se está registrando\n";
    }

    $freeUsers = DB::table('users')->where('roles', 'free')->count();
    $totalUsers = DB::table('users')->count();

    if ($freeUsers == $totalUsers) {
        echo "❌ TODOS los usuarios tienen roles 'free'\n";
        echo "   Posible problema: Los pagos exitosos no están actualizando los roles\n";
    }

    echo "\n7. VERIFICACIÓN DE COLUMNAS EN USERS\n";
    echo "Columna 'roles' existe: " . (in_array('roles', Schema::getColumnListing('users')) ? 'SÍ' : 'NO') . "\n";
    echo "Columna 'plan' existe: " . (in_array('plan', Schema::getColumnListing('users')) ? 'SÍ' : 'NO') . "\n";
    echo "Columna 'plan_code' existe: " . (in_array('plan_code', Schema::getColumnListing('users')) ? 'SÍ' : 'NO') . "\n";

    echo "\n=== FIN DEL ANÁLISIS ===\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
