<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

try {
    echo "=== SIMULADOR DE EXPIRACIÓN DE PLAN ===\n\n";

    // 1. Buscar un usuario con plan basic para hacer la prueba
    $basicUser = DB::table('users')->where('roles', 'basic')->first();

    if (!$basicUser) {
        echo "❌ No se encontró ningún usuario con rol 'basic'\n";
        exit(1);
    }

    echo "👤 Usuario de prueba: {$basicUser->email} (ID: {$basicUser->id})\n";
    echo "📦 Estado actual:\n";
    echo "   - Rol: {$basicUser->roles}\n";
    echo "   - Plan: {$basicUser->plan}\n";
    echo "   - Plan Code: {$basicUser->plan_code}\n";
    echo "   - Expira: {$basicUser->plan_expires_at}\n\n";

    // 2. Actualizar la fecha de expiración a hace 1 día (forzar expiración)
    $yesterdayDate = Carbon::now()->subDays(1);

    echo "⏰ Forzando expiración del plan...\n";
    echo "Cambiando plan_expires_at de '{$basicUser->plan_expires_at}' a '{$yesterdayDate}'\n\n";

    DB::table('users')
        ->where('id', $basicUser->id)
        ->update(['plan_expires_at' => $yesterdayDate]);

    // 3. Ejecutar el comando de actualización de planes expirados
    echo "🔄 Ejecutando comando de actualización de planes expirados...\n";

    $exitCode = Artisan::call('plans:update-expired');
    echo "Comando ejecutado con código: {$exitCode}\n";

    // Mostrar output del comando
    $output = Artisan::output();
    if ($output) {
        echo "Output del comando:\n";
        echo $output;
    }

    echo "\n";

    // 4. Verificar el resultado
    echo "📊 VERIFICANDO RESULTADOS DESPUÉS DE LA EXPIRACIÓN...\n";

    $updatedUser = DB::table('users')->where('id', $basicUser->id)->first();

    echo "Estado anterior:\n";
    echo "   - Rol: {$basicUser->roles}\n";
    echo "   - Plan: {$basicUser->plan}\n";
    echo "   - Plan Code: {$basicUser->plan_code}\n";

    echo "\nEstado actual:\n";
    echo "   - Rol: {$updatedUser->roles}\n";
    echo "   - Plan: {$updatedUser->plan}\n";
    echo "   - Plan Code: {$updatedUser->plan_code}\n";
    echo "   - Expira: {$updatedUser->plan_expires_at}\n";

    // 5. Verificar si la actualización fue exitosa
    echo "\n✅ VERIFICACIÓN DE LA CORRECCIÓN:\n";

    if ($updatedUser->roles === 'free') {
        echo "✅ Rol actualizado correctamente a 'free'\n";
    } else {
        echo "❌ Rol NO actualizado (sigue siendo '{$updatedUser->roles}')\n";
    }

    if ($updatedUser->plan === 'free') {
        echo "✅ Plan actualizado correctamente a 'free'\n";
    } else {
        echo "❌ Plan NO actualizado (sigue siendo '{$updatedUser->plan}')\n";
    }

    if ($updatedUser->plan_code === 'free') {
        echo "✅ Plan Code actualizado correctamente a 'free'\n";
    } else {
        echo "❌ Plan Code NO actualizado (sigue siendo '{$updatedUser->plan_code}')\n";
    }

    if ($updatedUser->roles === 'free' && $updatedUser->plan === 'free' && $updatedUser->plan_code === 'free') {
        echo "\n🎉 ¡CORRECCIÓN EXITOSA! Todas las columnas se actualizaron correctamente al expirar el plan.\n";
    } else {
        echo "\n⚠️ Algunas columnas no se actualizaron correctamente.\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
