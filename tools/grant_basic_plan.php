<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== OTORGAR PLAN BASIC ===\n\n";

$email = 'goku03278@gmail.com';
$days = 3;

// Buscar usuario
echo "📧 Buscando usuario con email: {$email}\n";
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado:\n";
echo "   - ID: {$user->id}\n";
echo "   - Username: {$user->username}\n";
echo "   - Email: {$user->email}\n";
echo "   - Plan actual: " . ($user->plan ?? 'No definido') . "\n";

// Verificar estructura de la tabla users
echo "\n📋 Verificando campos de plan en la tabla users...\n";
$userTable = DB::select("DESCRIBE users");
$planFields = [];
foreach ($userTable as $column) {
    if (strpos(strtolower($column->Field), 'plan') !== false ||
        strpos(strtolower($column->Field), 'subscription') !== false ||
        strpos(strtolower($column->Field), 'expire') !== false) {
        $planFields[] = $column->Field;
        echo "   - {$column->Field}: {$column->Type}\n";
    }
}

if (empty($planFields)) {
    echo "❌ No se encontraron campos relacionados con planes en la tabla users\n";

    // Buscar en otras tablas
    echo "\n🔍 Buscando tablas relacionadas con planes...\n";
    $tables = DB::select("SHOW TABLES");
    $planTables = [];

    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if (strpos(strtolower($tableName), 'plan') !== false ||
            strpos(strtolower($tableName), 'subscription') !== false) {
            $planTables[] = $tableName;
            echo "   - Encontrada: {$tableName}\n";
        }
    }

    if (empty($planTables)) {
        echo "❌ No se encontraron tablas de planes. Agregando campo plan a users...\n";

        try {
            DB::statement("ALTER TABLE users ADD COLUMN plan VARCHAR(50) DEFAULT 'free'");
            DB::statement("ALTER TABLE users ADD COLUMN plan_expires_at TIMESTAMP NULL");
            echo "✅ Campos de plan agregados a la tabla users\n";
        } catch (Exception $e) {
            echo "❌ Error al agregar campos: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
} else {
    echo "✅ Campos de plan encontrados\n";
}

// Otorgar plan basic por 3 días
$expirationDate = Carbon::now()->addDays($days);

echo "\n🎯 Otorgando plan basic por {$days} días...\n";
echo "   - Fecha de expiración: {$expirationDate->format('Y-m-d H:i:s')}\n";

try {
    $user->update([
        'plan' => 'basic',
        'plan_expires_at' => $expirationDate
    ]);

    echo "✅ Plan basic otorgado exitosamente\n";
    echo "   - Usuario: {$user->username} ({$user->email})\n";
    echo "   - Plan: basic\n";
    echo "   - Expira: {$expirationDate->format('Y-m-d H:i:s')}\n";
    echo "   - Días restantes: {$days}\n";

} catch (Exception $e) {
    echo "❌ Error al otorgar plan: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 ¡Proceso completado exitosamente!\n";
