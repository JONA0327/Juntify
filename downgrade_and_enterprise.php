<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

try {
    echo "=== DEGRADACIÓN DE USUARIOS BASIC Y ASIGNACIÓN ENTERPRISE ===\n\n";

    // 1. Verificar usuarios basic actuales
    echo "📊 VERIFICANDO USUARIOS BASIC ACTUALES...\n";

    $usuariosBasic = DB::table('users')
        ->where('roles', 'basic')
        ->select('id', 'email', 'roles', 'plan', 'plan_code', 'plan_expires_at')
        ->get();

    echo "Usuarios con rol 'basic' encontrados: " . count($usuariosBasic) . "\n\n";

    if (count($usuariosBasic) > 0) {
        echo "👥 USUARIOS BASIC A DEGRADAR:\n";
        foreach ($usuariosBasic as $user) {
            echo "- {$user->email}: rol={$user->roles}, plan={$user->plan}, plan_code={$user->plan_code}\n";
        }
        echo "\n";
    }

    // 2. Verificar usuario goku03278@gmail.com
    echo "🔍 VERIFICANDO USUARIO goku03278@gmail.com...\n";

    $gokuUser = DB::table('users')
        ->where('email', 'goku03278@gmail.com')
        ->select('id', 'email', 'roles', 'plan', 'plan_code', 'plan_expires_at')
        ->first();

    if (!$gokuUser) {
        echo "❌ Usuario goku03278@gmail.com no encontrado\n";
        exit(1);
    }

    echo "Usuario encontrado:\n";
    echo "- Email: {$gokuUser->email}\n";
    echo "- Rol actual: {$gokuUser->roles}\n";
    echo "- Plan actual: {$gokuUser->plan}\n";
    echo "- Plan Code actual: {$gokuUser->plan_code}\n";
    echo "- Expira: {$gokuUser->plan_expires_at}\n\n";

    // 3. Confirmación
    echo "🔧 ¿Proceder con los cambios? (y/N):\n";
    echo "- Degradar " . count($usuariosBasic) . " usuarios basic a free\n";
    echo "- Dar a goku03278@gmail.com rol enterprise por 3 días\n\n";

    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) !== 'y') {
        echo "❌ Operación cancelada.\n";
        exit(0);
    }

    echo "\n🔄 INICIANDO CAMBIOS...\n\n";

    // 4. Degradar usuarios basic a free
    if (count($usuariosBasic) > 0) {
        echo "1️⃣ DEGRADANDO USUARIOS BASIC A FREE...\n";

        foreach ($usuariosBasic as $user) {
            echo "   Degradando {$user->email}:\n";
            echo "     Antes: rol={$user->roles}, plan={$user->plan}, plan_code={$user->plan_code}\n";

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'roles' => 'free',
                    'plan' => 'free',
                    'plan_code' => 'free',
                    'plan_expires_at' => null
                ]);

            echo "     Después: rol=free, plan=free, plan_code=free, expires=null\n\n";
        }

        echo "✅ {$usuariosBasic->count()} usuarios degradados exitosamente\n\n";
    }

    // 5. Asignar enterprise a goku03278@gmail.com por 3 días
    echo "2️⃣ ASIGNANDO ROL ENTERPRISE A goku03278@gmail.com...\n";

    $now = Carbon::now();
    $expiresAt = $now->copy()->addDays(3);

    echo "   Cambiando {$gokuUser->email}:\n";
    echo "     Antes: rol={$gokuUser->roles}, plan={$gokuUser->plan}, plan_code={$gokuUser->plan_code}\n";
    echo "     Fecha actual: {$now->format('Y-m-d H:i:s')}\n";
    echo "     Nueva expiración: {$expiresAt->format('Y-m-d H:i:s')} (3 días)\n";

    DB::table('users')
        ->where('id', $gokuUser->id)
        ->update([
            'roles' => 'enterprise',
            'plan' => 'enterprise',
            'plan_code' => 'enterprise',
            'plan_expires_at' => $expiresAt
        ]);

    echo "     Después: rol=enterprise, plan=enterprise, plan_code=enterprise\n\n";
    echo "✅ Usuario goku03278@gmail.com actualizado a enterprise por 3 días\n\n";

    // 6. Verificación final
    echo "3️⃣ VERIFICACIÓN FINAL...\n";

    // Verificar que no queden usuarios basic
    $remainingBasic = DB::table('users')->where('roles', 'basic')->count();
    echo "Usuarios basic restantes: {$remainingBasic}\n";

    // Verificar usuario goku
    $gokuUpdated = DB::table('users')
        ->where('email', 'goku03278@gmail.com')
        ->select('email', 'roles', 'plan', 'plan_code', 'plan_expires_at')
        ->first();

    echo "Usuario goku03278@gmail.com:\n";
    echo "- Rol: {$gokuUpdated->roles}\n";
    echo "- Plan: {$gokuUpdated->plan}\n";
    echo "- Plan Code: {$gokuUpdated->plan_code}\n";
    echo "- Expira: {$gokuUpdated->plan_expires_at}\n\n";

    // 7. Distribución final
    echo "📊 DISTRIBUCIÓN FINAL:\n";
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

    echo "\n🎉 CAMBIOS COMPLETADOS EXITOSAMENTE!\n";
    echo "- Usuarios basic degradados a free ✅\n";
    echo "- goku03278@gmail.com con rol enterprise por 3 días ✅\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
