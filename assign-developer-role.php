<?php

/*
|--------------------------------------------------------------------------
| Asignar rol de Developer
|--------------------------------------------------------------------------
|
| Script para asignar el rol de "developer" al usuario jona03278@gmail.com
|
*/

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Configurar la aplicación Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "🔍 Buscando usuario con email: jona03278@gmail.com\n";
    
    // Buscar el usuario por email
    $user = User::where('email', 'jona03278@gmail.com')->first();
    
    if (!$user) {
        echo "❌ Error: No se encontró ningún usuario con el email jona03278@gmail.com\n";
        echo "💡 Asegúrate de que el usuario esté registrado en el sistema\n";
        exit(1);
    }
    
    echo "✅ Usuario encontrado:\n";
    echo "   - ID: {$user->id}\n";
    echo "   - Username: {$user->username}\n";
    echo "   - Nombre: {$user->full_name}\n";
    echo "   - Email: {$user->email}\n";
    echo "   - Rol actual: {$user->roles}\n";
    echo "   - Plan actual: {$user->plan}\n";
    echo "   - Código de plan: {$user->plan_code}\n";
    
    // Verificar si ya tiene el rol de developer
    if ($user->roles === 'developer' && $user->plan_code === 'developer') {
        echo "ℹ️  El usuario ya tiene el rol y plan de 'developer'\n";
        echo "✨ No hay cambios necesarios\n";
        exit(0);
    }
    
    // Guardar los valores anteriores para referencia
    $oldRole = $user->roles;
    $oldPlan = $user->plan;
    $oldPlanCode = $user->plan_code;
    
    // Asignar el rol de developer
    echo "\n🔄 Asignando rol de 'developer' y plan 'developer' al usuario...\n";
    
    $user->roles = 'developer';
    $user->plan = 'developer';
    $user->plan_code = 'developer';
    $user->save();
    
    // Verificar que el cambio se guardó correctamente
    $user->refresh();
    
    if ($user->roles === 'developer' && $user->plan_code === 'developer') {
        echo "✅ ¡Éxito! El rol y plan han sido actualizados:\n";
        echo "   - Rol anterior: {$oldRole} → {$user->roles}\n";
        echo "   - Plan anterior: {$oldPlan} → {$user->plan}\n";
        echo "   - Código anterior: {$oldPlanCode} → {$user->plan_code}\n";
        echo "\n🎉 El usuario jona03278@gmail.com ahora tiene rol y plan de 'developer'\n";
        echo "🔑 Esto le da acceso al panel administrativo y funciones de desarrollador\n";
        echo "💎 En la interfaz ahora aparecerá como plan 'Developer'\n";
    } else {
        echo "❌ Error: El rol o plan no se actualizó correctamente\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Error inesperado: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
    exit(1);
}

echo "\n✨ Proceso completado con éxito\n";