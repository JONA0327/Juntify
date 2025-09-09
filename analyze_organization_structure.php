<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "=== ANÁLISIS DE ORGANIZACIÓN Y GRUPOS ===\n\n";

// 1. Verificar el usuario actual (primer usuario)
$currentUser = User::first();
echo "1. Usuario actual:\n";
echo "   ID: {$currentUser->id}\n";
echo "   Nombre: {$currentUser->full_name}\n";
echo "   Email: {$currentUser->email}\n";
echo "   Organization ID: {$currentUser->current_organization_id}\n\n";

// 2. Buscar TODOS los usuarios de la misma organización
echo "2. Usuarios de la misma organización ({$currentUser->current_organization_id}):\n";
$sameOrgUsers = User::where('current_organization_id', $currentUser->current_organization_id)
    ->where('id', '!=', $currentUser->id)
    ->get(['id', 'full_name', 'email', 'current_organization_id']);

echo "   Total encontrados: " . $sameOrgUsers->count() . "\n";
foreach ($sameOrgUsers as $user) {
    echo "   - {$user->full_name} ({$user->email})\n";
}

// 3. Verificar si existe tabla de grupos
echo "\n3. Verificando estructura de grupos:\n";
try {
    $groupTables = DB::select("SHOW TABLES LIKE '%group%'");
    echo "   Tablas relacionadas con grupos:\n";
    foreach ($groupTables as $table) {
        $tableName = array_values((array)$table)[0];
        echo "   - {$tableName}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 4. Si existe tabla groups, mostrar estructura
try {
    $groups = DB::table('groups')->limit(5)->get();
    echo "\n4. Grupos existentes:\n";
    foreach ($groups as $group) {
        echo "   - ID: {$group->id}, Nombre: " . ($group->name ?? $group->title ?? 'Sin nombre') . "\n";
    }
} catch (Exception $e) {
    echo "\n4. Tabla groups no existe o error: " . $e->getMessage() . "\n";
}

// 5. Verificar relación usuario-grupo
try {
    $userGroups = DB::table('user_groups')->limit(5)->get();
    echo "\n5. Relaciones usuario-grupo:\n";
    foreach ($userGroups as $relation) {
        echo "   - Usuario: {$relation->user_id}, Grupo: {$relation->group_id}\n";
    }
} catch (Exception $e) {
    echo "\n5. Tabla user_groups no existe o error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL ANÁLISIS ===\n";
