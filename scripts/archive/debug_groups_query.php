<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== DEBUG: CONSULTA DE GRUPOS ===\n\n";

// Obtener el primer usuario para saber su organización
$user = User::first();
echo "Usuario de prueba: {$user->full_name}\n";
echo "Organization ID: '{$user->current_organization_id}'\n\n";

// Primero, veamos todos los grupos que existen
echo "🏢 TODOS LOS GRUPOS EN LA BASE DE DATOS:\n";
$allGroups = DB::table('groups')->select('id', 'nombre_grupo', 'id_organizacion')->get();
foreach ($allGroups as $group) {
    echo "   - ID: {$group->id}, Nombre: '{$group->nombre_grupo}', Org ID: '{$group->id_organizacion}'\n";
}

echo "\n👥 RELACIONES GRUPO-USUARIO:\n";
$groupUsers = DB::table('group_user')
    ->join('groups', 'group_user.id_grupo', '=', 'groups.id')
    ->join('users', 'group_user.user_id', '=', 'users.id')
    ->select('users.full_name', 'users.email', 'groups.nombre_grupo', 'group_user.rol')
    ->get();

foreach ($groupUsers as $relation) {
    echo "   - Usuario: {$relation->full_name} ({$relation->email})\n";
    echo "     📂 Grupo: {$relation->nombre_grupo} | Rol: {$relation->rol}\n\n";
}

echo "\n🔍 CONSULTA ACTUAL DEL CONTROLADOR:\n";
// Replicar la consulta exacta del controlador
$users = User::select([
        'users.id',
        'users.full_name as name',
        'users.email',
        'users.current_organization_id',
        'groups.nombre_grupo as group_name',
        'group_user.rol as group_role'
    ])
    ->leftJoin('group_user', 'users.id', '=', 'group_user.user_id')
    ->leftJoin('groups', 'group_user.id_grupo', '=', 'groups.id')
    ->where(function($query) use ($user) {
        if (!empty($user->current_organization_id)) {
            $query->where('users.current_organization_id', $user->current_organization_id);
        } else {
            // Si no tiene organización, buscar por grupos
            $query->whereExists(function($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('group_user')
                    ->whereRaw('group_user.user_id = users.id');
            });
        }
    })
    ->get();

echo "Resultados de la consulta ({$users->count()} usuarios):\n";
foreach ($users as $user) {
    echo "   - {$user->name} ({$user->email})\n";
    echo "     📂 Grupo: '{$user->group_name}' | Rol: '{$user->group_role}' | Org: '{$user->current_organization_id}'\n\n";
}
