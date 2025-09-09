<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "=== ANÁLISIS CORRECTO DE GRUPOS ===\n\n";

// 1. Usuario actual
$currentUser = User::first();
echo "1. Usuario actual:\n";
echo "   ID: {$currentUser->id}\n";
echo "   Nombre: {$currentUser->full_name}\n";
echo "   Organization ID: '{$currentUser->current_organization_id}'\n\n";

// 2. Grupos de la organización del usuario actual
echo "2. Grupos en la organización del usuario:\n";
try {
    $orgId = $currentUser->current_organization_id ?: 1; // Si está vacío, usar 1
    $groups = DB::table('groups')->where('id_organizacion', $orgId)->get();
    echo "   Organización ID: {$orgId}\n";
    echo "   Grupos encontrados: " . $groups->count() . "\n";

    foreach ($groups as $group) {
        echo "   - Grupo ID: {$group->id}, Nombre: '{$group->nombre_grupo}', Miembros: {$group->miembros}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 3. Usuarios en cada grupo (con nombres de usuario)
echo "\n3. Usuarios por grupo con detalles:\n";
try {
    $userGroups = DB::table('group_user')
        ->join('users', 'group_user.user_id', '=', 'users.id')
        ->select('group_user.id_grupo', 'group_user.rol', 'users.full_name', 'users.email', 'users.current_organization_id')
        ->limit(15)
        ->get();

    foreach ($userGroups as $relation) {
        echo "   - Grupo: {$relation->id_grupo}, Usuario: {$relation->full_name} ({$relation->email}), Rol: {$relation->rol}, Org: {$relation->current_organization_id}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 4. Contar usuarios por grupo (corregido)
echo "\n4. Resumen de usuarios por grupo:\n";
try {
    $userCounts = DB::table('group_user')
        ->select('id_grupo', DB::raw('COUNT(*) as user_count'))
        ->groupBy('id_grupo')
        ->get();

    foreach ($userCounts as $count) {
        $groupName = DB::table('groups')->where('id', $count->id_grupo)->value('nombre_grupo') ?? 'Sin nombre';
        echo "   - Grupo {$count->id_grupo} ({$groupName}): {$count->user_count} usuarios\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL ANÁLISIS ===\n";
