<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "=== Testing exact exists() query ===\n";

$meetingId = 56;
$user = User::where('username', 'Jonalp0327')->first();

echo "Usuario: {$user->username} (ID: {$user->id})\n";
echo "Meeting ID: {$meetingId}\n\n";

// Consulta exacta del controlador
$containerAccess = DB::table('meeting_content_relations')
    ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
    ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
    ->leftJoin('group_user', function($join) use ($user) {
        $join->on('groups.id', '=', 'group_user.id_grupo')
             ->where('group_user.user_id', '=', $user->id);
    })
    ->leftJoin('organizations', 'groups.id_organizacion', '=', 'organizations.id')
    ->where('meeting_content_relations.meeting_id', $meetingId)
    ->where('meeting_content_containers.is_active', true)
    ->where(function($query) use ($user) {
        $query->where('meeting_content_containers.username', $user->username)
              ->orWhereNotNull('group_user.user_id')
              ->orWhere('organizations.admin_id', $user->id);
    })
    ->exists();

echo "Resultado exists(): " . ($containerAccess ? "TRUE" : "FALSE") . "\n\n";

// También contar resultados
$count = DB::table('meeting_content_relations')
    ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
    ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
    ->leftJoin('group_user', function($join) use ($user) {
        $join->on('groups.id', '=', 'group_user.id_grupo')
             ->where('group_user.user_id', '=', $user->id);
    })
    ->leftJoin('organizations', 'groups.id_organizacion', '=', 'organizations.id')
    ->where('meeting_content_relations.meeting_id', $meetingId)
    ->where('meeting_content_containers.is_active', true)
    ->where(function($query) use ($user) {
        $query->where('meeting_content_containers.username', $user->username)
              ->orWhereNotNull('group_user.user_id')
              ->orWhere('organizations.admin_id', $user->id);
    })
    ->count();

echo "Resultado count(): {$count}\n\n";

// Verificar las condiciones individualmente
echo "Verificando condiciones individuales:\n";

// Condición 1: Es creador del contenedor
$isCreator = DB::table('meeting_content_relations')
    ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
    ->where('meeting_content_relations.meeting_id', $meetingId)
    ->where('meeting_content_containers.username', $user->username)
    ->exists();
echo "1. Es creador del contenedor: " . ($isCreator ? "TRUE" : "FALSE") . "\n";

// Condición 2: Es miembro del grupo
$isMember = DB::table('meeting_content_relations')
    ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
    ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
    ->join('group_user', 'groups.id', '=', 'group_user.id_grupo')
    ->where('meeting_content_relations.meeting_id', $meetingId)
    ->where('group_user.user_id', $user->id)
    ->exists();
echo "2. Es miembro del grupo: " . ($isMember ? "TRUE" : "FALSE") . "\n";
