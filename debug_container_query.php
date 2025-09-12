<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "=== Debugging container access query ===\n";

$meetingId = 56;
$user = User::where('username', 'Jonalp0327')->first();

if (!$user) {
    echo "Usuario no encontrado\n";
    exit;
}

echo "Usuario: {$user->username} (ID: {$user->id})\n";
echo "Meeting ID: {$meetingId}\n\n";

// Paso 1: Verificar si la reunión está en un contenedor
echo "1. ¿La reunión está en un contenedor?\n";
$inContainer = DB::table('meeting_content_relations')
    ->where('meeting_id', $meetingId)
    ->exists();
echo "   Resultado: " . ($inContainer ? "SÍ" : "NO") . "\n\n";

if ($inContainer) {
    // Paso 2: Obtener detalles del contenedor
    echo "2. Detalles del contenedor:\n";
    $containerInfo = DB::table('meeting_content_relations')
        ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
        ->where('meeting_id', $meetingId)
        ->select('meeting_content_containers.*')
        ->first();

    if ($containerInfo) {
        echo "   Container ID: {$containerInfo->id}\n";
        echo "   Nombre: {$containerInfo->name}\n";
        echo "   Dueño: {$containerInfo->username}\n";
        echo "   Group ID: {$containerInfo->group_id}\n";
        echo "   Activo: " . ($containerInfo->is_active ? "SÍ" : "NO") . "\n\n";

        // Paso 3: Verificar el grupo
        echo "3. ¿El usuario está en el grupo?\n";
        $inGroup = DB::table('group_user')
            ->where('id_grupo', $containerInfo->group_id)
            ->where('user_id', $user->id)
            ->exists();
        echo "   Resultado: " . ($inGroup ? "SÍ" : "NO") . "\n\n";

        // Paso 4: Ejecutar la consulta completa paso a paso
        echo "4. Consulta completa paso a paso:\n";

        $query = DB::table('meeting_content_relations')
            ->join('meeting_content_containers', 'meeting_content_relations.container_id', '=', 'meeting_content_containers.id')
            ->join('groups', 'meeting_content_containers.group_id', '=', 'groups.id')
            ->leftJoin('group_user', function($join) use ($user) {
                $join->on('groups.id', '=', 'group_user.id_grupo')
                     ->where('group_user.user_id', '=', $user->id);
            })
            ->leftJoin('organizations', 'groups.id_organizacion', '=', 'organizations.id')
            ->where('meeting_content_relations.meeting_id', $meetingId)
            ->where('meeting_content_containers.is_active', true);

        // Ver el SQL generado
        echo "   SQL: " . $query->toSql() . "\n";
        echo "   Bindings: " . json_encode($query->getBindings()) . "\n\n";

        // Ejecutar con where conditions
        $result = $query->where(function($query) use ($user) {
                $query->where('meeting_content_containers.username', $user->username)
                      ->orWhereNotNull('group_user.user_id')
                      ->orWhere('organizations.admin_id', $user->id);
            })
            ->get();

        echo "   Resultados encontrados: " . $result->count() . "\n";
        if ($result->count() > 0) {
            foreach ($result as $row) {
                echo "   - Group user ID: " . ($row->user_id ?? 'NULL') . "\n";
                echo "   - Container owner: " . $row->username . "\n";
                echo "   - Org admin ID: " . ($row->admin_id ?? 'NULL') . "\n";
            }
        }
    }
}
