<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Probando acceso a reuniones de contenedores ===\n";

// Buscar una reunión que esté en un contenedor
$meetingInContainer = DB::table('meeting_content_relations')
    ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
    ->join('transcriptions_laravel', 'meeting_content_relations.meeting_id', '=', 'transcriptions_laravel.id')
    ->select('transcriptions_laravel.id', 'transcriptions_laravel.meeting_name', 'transcriptions_laravel.username as meeting_owner', 'meeting_content_containers.username as container_owner', 'meeting_content_containers.group_id')
    ->first();

if ($meetingInContainer) {
    echo "Reunión encontrada en contenedor:\n";
    echo "ID: {$meetingInContainer->id}\n";
    echo "Nombre: {$meetingInContainer->meeting_name}\n";
    echo "Dueño reunión: {$meetingInContainer->meeting_owner}\n";
    echo "Dueño contenedor: {$meetingInContainer->container_owner}\n";
    echo "Grupo ID: {$meetingInContainer->group_id}\n";

    // Buscar usuarios en el grupo
    $groupUsers = DB::table('group_user')
        ->join('users', 'group_user.user_id', '=', 'users.id')
        ->where('group_user.id_grupo', $meetingInContainer->group_id)
        ->where('users.username', '!=', $meetingInContainer->meeting_owner)
        ->select('users.id', 'users.username', 'users.email', 'group_user.rol')
        ->get();

    echo "\nUsuarios en el grupo (que no son dueños de la reunión):\n";
    foreach ($groupUsers as $user) {
        echo "- {$user->username} ({$user->email}) - Rol: {$user->rol}\n";
    }

    echo "\n=== Probando consulta de acceso por contenedor ===\n";
    if (!$groupUsers->isEmpty()) {
        $testUser = $groupUsers->first();
        echo "Probando acceso para usuario: {$testUser->username}\n";

        $hasAccess = DB::table('meeting_content_relations')
            ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
            ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
            ->leftJoin('group_user', function($join) use ($testUser) {
                $join->on('groups.id', '=', 'group_user.id_grupo')
                     ->where('group_user.user_id', '=', $testUser->id);
            })
            ->leftJoin('organizations', 'groups.id_organizacion', '=', 'organizations.id')
            ->where('meeting_content_relations.meeting_id', $meetingInContainer->id)
            ->where('meeting_content_containers.is_active', true)
            ->where(function($query) use ($testUser) {
                $query->where('meeting_content_containers.username', $testUser->username) // Es creador del contenedor
                      ->orWhereNotNull('group_user.user_id') // Es miembro del grupo
                      ->orWhere('organizations.admin_id', $testUser->id); // Es admin de la organización
            })
            ->exists();

        echo "¿El usuario {$testUser->username} tiene acceso? " . ($hasAccess ? "SÍ" : "NO") . "\n";
    }
} else {
    echo "No se encontraron reuniones en contenedores.\n";
}
