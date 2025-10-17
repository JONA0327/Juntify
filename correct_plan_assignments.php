<?php

require_once __DIR__ . '/vendor/autoload.php';

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CORRECCIÓN DE ASIGNACIONES DE PLANES ===\n\n";

try {
    // 1. Buscar usuario goku03278@gmail.com
    echo "1. Buscando usuario goku03278@gmail.com...\n";
    $gokuUser = DB::table('users')->where('email', 'goku03278@gmail.com')->first();

    if (!$gokuUser) {
        echo "❌ Error: Usuario goku03278@gmail.com no encontrado.\n";
        exit(1);
    }

    echo "✅ Usuario encontrado:\n";
    echo "   - ID: {$gokuUser->id}\n";
    echo "   - Email: {$gokuUser->email}\n";
    echo "   - Rol actual: {$gokuUser->roles}\n";
    echo "   - Plan expira: " . ($gokuUser->plan_expires_at ?? 'No definido') . "\n\n";

    // 2. Buscar usuario jon0327
    echo "2. Buscando usuario jon0327...\n";
    $jonUser = DB::table('users')->where('email', 'LIKE', '%jon%')->first();

    if (!$jonUser) {
        echo "❌ Error: Usuario jon0327 no encontrado. Buscando por email exacto...\n";
        // Buscar por otros posibles emails
        $jonUser = DB::table('users')->where('id', '58c833cb-df4c-4129-a88a-72aaa3b2254e')->first();
    }

    if (!$jonUser) {
        echo "❌ Error: Usuario jon0327 no encontrado.\n";
        exit(1);
    }

    echo "✅ Usuario jon encontrado:\n";
    echo "   - ID: {$jonUser->id}\n";
    echo "   - Email: {$jonUser->email}\n";
    echo "   - Rol actual: {$jonUser->roles}\n";
    echo "   - Plan expira: " . ($jonUser->plan_expires_at ?? 'No definido') . "\n\n";

    // 3. Actualizar goku03278@gmail.com a plan basic (24 horas)
    echo "3. Asignando plan BASIC a goku03278@gmail.com...\n";
    $basicExpiration = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $updated = DB::table('users')
        ->where('email', 'goku03278@gmail.com')
        ->update([
            'roles' => 'basic',
            'plan_expires_at' => $basicExpiration,
            'updated_at' => now()
        ]);

    if ($updated) {
        echo "✅ Plan BASIC asignado exitosamente a goku03278@gmail.com\n";
        echo "   - Nuevo rol: basic\n";
        echo "   - Expira: {$basicExpiration}\n\n";
    } else {
        echo "❌ Error al actualizar plan de goku03278@gmail.com\n";
    }

    // 4. Restaurar jon0327 a developer
    echo "4. Restaurando rol DEVELOPER a jon0327...\n";

    $updated = DB::table('users')
        ->where('id', $jonUser->id)
        ->update([
            'roles' => 'developer',
            'plan_expires_at' => null, // Sin límite de tiempo para developer
            'updated_at' => now()
        ]);

    if ($updated) {
        echo "✅ Rol DEVELOPER restaurado exitosamente a jon0327\n";
        echo "   - Nuevo rol: developer\n";
        echo "   - Sin fecha de expiración\n\n";
    } else {
        echo "❌ Error al actualizar rol de jon0327\n";
    }

    // 5. Verificar cambios
    echo "5. VERIFICACIÓN FINAL:\n";
    echo "---------------------\n";

    // Verificar goku
    $gokuUpdated = DB::table('users')->where('email', 'goku03278@gmail.com')->first();
    echo "goku03278@gmail.com:\n";
    echo "   - Rol: {$gokuUpdated->roles}\n";
    echo "   - Expira: {$gokuUpdated->plan_expires_at}\n";
    echo "   - Beneficios BASIC activos:\n";
    echo "     * 10 consultas AI por día\n";
    echo "     * 5 documentos por día\n";
    echo "     * Gestión completa de tareas\n";
    echo "     * Transcripciones ilimitadas\n\n";

    // Verificar jon
    $jonUpdated = DB::table('users')->where('id', $jonUser->id)->first();
    echo "jon0327:\n";
    echo "   - Rol: {$jonUpdated->roles}\n";
    echo "   - Expira: " . ($jonUpdated->plan_expires_at ?? 'Sin límite') . "\n";
    echo "   - Beneficios DEVELOPER activos:\n";
    echo "     * Acceso completo al sistema\n";
    echo "     * Sin límites de funcionalidades\n";
    echo "     * Acceso de desarrollador\n\n";

    echo "🎉 CORRECCIÓN COMPLETADA EXITOSAMENTE\n";
    echo "📋 INSTRUCCIONES PARA LOS USUARIOS:\n";
    echo "   - goku03278@gmail.com: Recarga las páginas de Juntify para ver los beneficios del plan BASIC\n";
    echo "   - jon0327: Recarga las páginas para recuperar el acceso completo de DEVELOPER\n";

} catch (Exception $e) {
    echo "❌ Error durante la corrección: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
