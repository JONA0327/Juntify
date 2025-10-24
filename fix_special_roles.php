<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "=== CORRECCIÓN DE PLANES PARA USUARIOS ESPECIALES ===\n\n";

    // Roles especiales que necesitan corrección
    $rolesEspeciales = ['developer', 'founder', 'superadmin'];

    echo "📊 ANALIZANDO USUARIOS ESPECIALES...\n";

    $usuariosEspeciales = DB::table('users')
        ->whereIn('roles', $rolesEspeciales)
        ->select('id', 'email', 'roles', 'plan', 'plan_code')
        ->get();

    echo "Total usuarios especiales encontrados: " . count($usuariosEspeciales) . "\n\n";

    // Mostrar usuarios que necesitan corrección
    $necesitanCorreccion = [];

    foreach ($usuariosEspeciales as $user) {
        if ($user->plan !== $user->roles || $user->plan_code !== $user->roles) {
            $necesitanCorreccion[] = $user;
            echo "❌ {$user->email} ({$user->roles}):\n";
            echo "   Plan actual: {$user->plan} (debería ser: {$user->roles})\n";
            echo "   Plan Code actual: {$user->plan_code} (debería ser: {$user->roles})\n\n";
        } else {
            echo "✅ {$user->email} ({$user->roles}) - Ya está correcto\n";
        }
    }

    if (count($necesitanCorreccion) === 0) {
        echo "\n🎉 Todos los usuarios especiales ya tienen los planes correctos!\n";
        exit(0);
    }

    echo "\n🔧 ¿Proceder con la corrección de " . count($necesitanCorreccion) . " usuarios? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);

    if (strtolower($response) !== 'y') {
        echo "❌ Operación cancelada.\n";
        exit(0);
    }

    echo "\n🔄 INICIANDO CORRECCIÓN...\n\n";

    $corregidos = 0;

    foreach ($necesitanCorreccion as $user) {
        echo "Corrigiendo {$user->email} ({$user->roles}):\n";
        echo "   Antes: plan={$user->plan}, plan_code={$user->plan_code}\n";

        // Para roles especiales, plan y plan_code deben ser iguales al rol
        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'plan' => $user->roles,
                'plan_code' => $user->roles
            ]);

        echo "   Después: plan={$user->roles}, plan_code={$user->roles}\n\n";
        $corregidos++;
    }

    // Verificación final
    echo "📊 VERIFICACIÓN FINAL...\n";

    $usuariosVerificacion = DB::table('users')
        ->whereIn('roles', $rolesEspeciales)
        ->select('email', 'roles', 'plan', 'plan_code')
        ->get();

    $todosCorrecto = true;

    foreach ($usuariosVerificacion as $user) {
        if ($user->plan === $user->roles && $user->plan_code === $user->roles) {
            echo "✅ {$user->email} ({$user->roles}): plan={$user->plan}, plan_code={$user->plan_code}\n";
        } else {
            echo "❌ {$user->email} ({$user->roles}): plan={$user->plan}, plan_code={$user->plan_code}\n";
            $todosCorrecto = false;
        }
    }

    if ($todosCorrecto) {
        echo "\n🎉 ¡CORRECCIÓN EXITOSA!\n";
        echo "Total usuarios corregidos: {$corregidos}\n";
        echo "Todos los usuarios especiales ahora tienen plan = plan_code = rol\n";
    } else {
        echo "\n⚠️ Algunos usuarios aún tienen inconsistencias\n";
    }

    // Mostrar distribución final
    echo "\n📊 DISTRIBUCIÓN FINAL POR ROLES ESPECIALES:\n";

    foreach ($rolesEspeciales as $rol) {
        $count = DB::table('users')->where('roles', $rol)->count();
        $planCount = DB::table('users')->where('roles', $rol)->where('plan', $rol)->count();
        $planCodeCount = DB::table('users')->where('roles', $rol)->where('plan_code', $rol)->count();

        echo "- {$rol}: {$count} usuarios (plan correcto: {$planCount}, plan_code correcto: {$planCodeCount})\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
