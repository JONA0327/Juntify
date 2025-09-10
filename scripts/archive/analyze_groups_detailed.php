<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ANÁLISIS DETALLADO DE GRUPOS ===\n\n";

// 1. Estructura de la tabla groups
echo "1. Estructura de la tabla groups:\n";
try {
    $groupColumns = DB::select('DESCRIBE groups');
    foreach ($groupColumns as $column) {
        echo "   - {$column->Field} ({$column->Type})\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 2. Todos los grupos
echo "\n2. Todos los grupos:\n";
try {
    $groups = DB::table('groups')->get();
    foreach ($groups as $group) {
        echo "   - ID: {$group->id}";
        if (isset($group->name)) echo ", Nombre: {$group->name}";
        if (isset($group->title)) echo ", Título: {$group->title}";
        if (isset($group->organization_id)) echo ", Org: {$group->organization_id}";
        echo "\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 3. Estructura de group_user
echo "\n3. Estructura de la tabla group_user:\n";
try {
    $groupUserColumns = DB::select('DESCRIBE group_user');
    foreach ($groupUserColumns as $column) {
        echo "   - {$column->Field} ({$column->Type})\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 4. Relaciones usuario-grupo
echo "\n4. Relaciones usuario-grupo (primeras 10):\n";
try {
    $userGroups = DB::table('group_user')->limit(10)->get();
    foreach ($userGroups as $relation) {
        echo "   - Usuario: {$relation->user_id}, Grupo: {$relation->group_id}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 5. Contar usuarios por grupo
echo "\n5. Usuarios por grupo:\n";
try {
    $userCounts = DB::table('group_user')
        ->select('group_id', DB::raw('COUNT(*) as user_count'))
        ->groupBy('group_id')
        ->get();

    foreach ($userCounts as $count) {
        $groupName = DB::table('groups')->where('id', $count->group_id)->value('name') ?? 'Sin nombre';
        echo "   - Grupo {$count->group_id} ({$groupName}): {$count->user_count} usuarios\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL ANÁLISIS ===\n";
