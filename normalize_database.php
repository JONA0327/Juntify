<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

try {
    echo "=== NORMALIZACIÓN COMPLETA DE LA BASE DE DATOS ===\n\n";

    // 1. Analizar el estado actual
    echo "📊 ANALIZANDO ESTADO ACTUAL...\n";

    $users = DB::table('users')->select('id', 'email', 'roles', 'plan', 'plan_code', 'plan_expires_at')->get();

    $inconsistencias = [];
    $usuariosExpirados = [];
    $planCodesIncorrectos = [];

    foreach ($users as $user) {
        // Verificar si el plan expiró
        $planExpired = $user->plan_expires_at && Carbon::parse($user->plan_expires_at)->isPast();

        if ($planExpired && $user->roles !== 'free') {
            $usuariosExpirados[] = $user;
        }

        // Verificar plan_code incorrecto (debe ser igual al rol)
        if ($user->plan_code !== $user->roles) {
            $planCodesIncorrectos[] = $user;
        }

        // Verificar inconsistencias generales
        if ($user->roles === 'free' && ($user->plan !== 'free' || $user->plan_code !== 'free')) {
            $inconsistencias[] = $user;
        }
    }

    echo "Usuarios con planes expirados que aún no son 'free': " . count($usuariosExpirados) . "\n";
    echo "Usuarios con plan_code incorrecto: " . count($planCodesIncorrectos) . "\n";
    echo "Usuarios con inconsistencias generales: " . count($inconsistencias) . "\n\n";

    // 2. Mostrar algunos ejemplos
    if (count($usuariosExpirados) > 0) {
        echo "📋 EJEMPLOS DE USUARIOS EXPIRADOS:\n";
        foreach (array_slice($usuariosExpirados, 0, 3) as $user) {
            echo "- {$user->email}: rol={$user->roles}, plan={$user->plan}, plan_code={$user->plan_code}, expiró={$user->plan_expires_at}\n";
        }
        echo "\n";
    }

    if (count($planCodesIncorrectos) > 0) {
        echo "📋 EJEMPLOS DE PLAN_CODES INCORRECTOS:\n";
        foreach (array_slice($planCodesIncorrectos, 0, 3) as $user) {
            echo "- {$user->email}: rol={$user->roles} ≠ plan_code={$user->plan_code}\n";
        }
        echo "\n";
    }

    // 3. Preguntar confirmación
    echo "🔧 ¿Proceder con la normalización? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) !== 'y') {
        echo "❌ Operación cancelada.\n";
        exit(0);
    }

    echo "\n🔄 INICIANDO NORMALIZACIÓN...\n\n";

    $corregidos = 0;

    // 4. Corregir usuarios con planes expirados
    if (count($usuariosExpirados) > 0) {
        echo "1️⃣ CORRIGIENDO USUARIOS CON PLANES EXPIRADOS...\n";

        foreach ($usuariosExpirados as $user) {
            echo "   Corrigiendo {$user->email}:\n";
            echo "     Antes: rol={$user->roles}, plan={$user->plan}, plan_code={$user->plan_code}\n";

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'roles' => 'free',
                    'plan' => 'free',
                    'plan_code' => 'free'
                ]);

            echo "     Después: rol=free, plan=free, plan_code=free\n\n";
            $corregidos++;
        }
    }

    // 5. Normalizar todos los plan_codes para que coincidan con los roles
    echo "2️⃣ NORMALIZANDO PLAN_CODES PARA COINCIDIR CON ROLES...\n";

    $mapeoRoles = [
        'free' => 'free',
        'basic' => 'basic',
        'business' => 'business',
        'enterprise' => 'enterprise',
        'developer' => 'developer',
        'founder' => 'founder',
        'superadmin' => 'superadmin'
    ];

    foreach ($planCodesIncorrectos as $user) {
        // Verificar que no esté expirado primero
        $planExpired = $user->plan_expires_at && Carbon::parse($user->plan_expires_at)->isPast();

        if ($planExpired) {
            // Si expiró, ponerlo como free
            echo "   Corrigiendo {$user->email} (EXPIRADO):\n";
            echo "     Antes: rol={$user->roles}, plan_code={$user->plan_code}, expiró={$user->plan_expires_at}\n";

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'roles' => 'free',
                    'plan' => 'free',
                    'plan_code' => 'free'
                ]);

            echo "     Después: rol=free, plan=free, plan_code=free\n\n";
        } else {
            // Si no expiró, normalizar plan_code al rol actual
            $correctPlanCode = $mapeoRoles[$user->roles] ?? $user->roles;
            $correctPlan = in_array($user->roles, ['free']) ? 'free' : 'basic';

            echo "   Normalizando {$user->email}:\n";
            echo "     Antes: rol={$user->roles}, plan={$user->plan}, plan_code={$user->plan_code}\n";

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'plan' => $correctPlan,
                    'plan_code' => $correctPlanCode
                ]);

            echo "     Después: rol={$user->roles}, plan={$correctPlan}, plan_code={$correctPlanCode}\n\n";
        }

        $corregidos++;
    }

    // 6. Verificación final
    echo "3️⃣ VERIFICACIÓN FINAL...\n";

    $usersAfter = DB::table('users')->select('roles', 'plan', 'plan_code', 'plan_expires_at')->get();

    $stillInconsistent = 0;
    $stillExpired = 0;

    foreach ($usersAfter as $user) {
        $planExpired = $user->plan_expires_at && Carbon::parse($user->plan_expires_at)->isPast();

        if ($planExpired && $user->roles !== 'free') {
            $stillExpired++;
        }

        if ($user->plan_code !== $user->roles) {
            $stillInconsistent++;
        }
    }

    echo "Usuarios con planes expirados sin corregir: {$stillExpired}\n";
    echo "Usuarios con plan_code inconsistente: {$stillInconsistent}\n";

    // 7. Resumen de distribución final
    echo "\n📊 DISTRIBUCIÓN FINAL:\n";
    $distribution = DB::table('users')
        ->select('roles', DB::raw('count(*) as total'))
        ->groupBy('roles')
        ->get();

    foreach ($distribution as $item) {
        echo "- {$item->roles}: {$item->total} usuarios\n";
    }

    echo "\n📊 DISTRIBUCIÓN DE PLAN_CODES:\n";
    $planCodeDistribution = DB::table('users')
        ->select('plan_code', DB::raw('count(*) as total'))
        ->groupBy('plan_code')
        ->get();

    foreach ($planCodeDistribution as $item) {
        echo "- {$item->plan_code}: {$item->total} usuarios\n";
    }

    echo "\n✅ NORMALIZACIÓN COMPLETA\n";
    echo "Total de usuarios corregidos: {$corregidos}\n";
    echo "🎉 Base de datos normalizada exitosamente!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
