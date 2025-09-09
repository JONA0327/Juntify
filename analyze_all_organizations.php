<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== ANÁLISIS DE ORGANIZACIONES ===\n\n";

// Ver todas las organizaciones únicas
echo "🏢 ORGANIZACIONES ÚNICAS:\n";
$orgs = DB::table('users')
    ->select('current_organization_id', DB::raw('COUNT(*) as count'))
    ->groupBy('current_organization_id')
    ->orderBy('count', 'desc')
    ->get();

foreach ($orgs as $org) {
    $orgId = $org->current_organization_id ?: 'Sin organización';
    echo "   - Org ID: '{$orgId}' -> {$org->count} usuarios\n";
}

echo "\n👥 USUARIOS POR ORGANIZACIÓN:\n";

foreach ($orgs as $org) {
    $orgId = $org->current_organization_id;
    $users = User::where('current_organization_id', $orgId)->get();

    $orgName = $orgId ?: 'Sin organización';
    echo "\n📋 Organización: '{$orgName}' ({$org->count} usuarios)\n";

    foreach ($users as $user) {
        echo "   - {$user->full_name} ({$user->email})\n";
    }
}

echo "\n🔍 USUARIOS EN GRUPOS:\n";
$usersInGroups = DB::table('group_user')
    ->join('users', 'group_user.user_id', '=', 'users.id')
    ->join('groups', 'group_user.id_grupo', '=', 'groups.id')
    ->select('users.full_name', 'users.email', 'users.current_organization_id', 'groups.nombre_grupo', 'groups.id_organizacion', 'group_user.rol')
    ->get();

foreach ($usersInGroups as $user) {
    echo "   - {$user->full_name} ({$user->email})\n";
    echo "     📊 Usuario Org: '{$user->current_organization_id}' | Grupo: '{$user->nombre_grupo}' | Grupo Org: '{$user->id_organizacion}' | Rol: {$user->rol}\n\n";
}
