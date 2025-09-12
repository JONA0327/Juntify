<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\MeetingContentContainer;
use App\Models\Group;
use App\Models\User;

// Configurar la aplicación Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Diagnóstico de Permisos del Contenedor ===\n\n";

// ID del contenedor que está fallando
$containerId = 5;

echo "Analizando contenedor ID: {$containerId}\n";

// Obtener información del contenedor
$container = MeetingContentContainer::with(['group', 'group.organization'])->find($containerId);

if (!$container) {
    echo "❌ El contenedor {$containerId} no existe\n";
    exit(1);
}

echo "✅ Contenedor encontrado:\n";
echo "   - ID: {$container->id}\n";
echo "   - Nombre: {$container->name}\n";
echo "   - Creador (username): {$container->username}\n";
echo "   - Group ID: {$container->group_id}\n";
echo "   - Activo: " . ($container->is_active ? 'Sí' : 'No') . "\n";

if ($container->group_id) {
    $group = $container->group;
    echo "   - Grupo: {$group->nombre}\n";
    echo "   - Organización ID: {$group->id_organizacion}\n";

    if ($group->organization) {
        echo "   - Organización: {$group->organization->nombre}\n";
        echo "   - Admin de Organización ID: {$group->organization->admin_id}\n";
    }
}

echo "\n=== Usuarios con Permisos ===\n";

// Mostrar el creador
$creator = User::where('username', $container->username)->first();
if ($creator) {
    echo "👤 Creador: {$creator->full_name} (ID: {$creator->id}, Username: {$creator->username})\n";
}

// Mostrar miembros del grupo si existe
if ($container->group_id) {
    echo "\n👥 Miembros del Grupo {$container->group_id}:\n";
    $members = DB::table('group_user')
        ->join('users', 'group_user.user_id', '=', 'users.id')
        ->where('group_user.id_grupo', $container->group_id)
        ->select('users.id', 'users.full_name', 'users.username', 'group_user.rol')
        ->get();

    foreach ($members as $member) {
        $canAddMeetings = in_array($member->rol, ['colaborador', 'administrador', 'full_meeting_access']) ? '✅' : '❌';
        echo "   - {$member->full_name} (ID: {$member->id}, Username: {$member->username}, Rol: {$member->rol}) {$canAddMeetings}\n";
    }

    if ($members->isEmpty()) {
        echo "   ⚠️  No hay miembros en este grupo\n";
    }
}

// Mostrar admin de organización si existe
if ($container->group && $container->group->organization) {
    $orgAdmin = User::find($container->group->organization->admin_id);
    if ($orgAdmin) {
        echo "\n🏢 Admin de Organización: {$orgAdmin->full_name} (ID: {$orgAdmin->id}, Username: {$orgAdmin->username})\n";
    }
}

echo "\n=== Verificación de Permisos ===\n";
echo "Para añadir reuniones a este contenedor, el usuario debe:\n";
echo "1. ✅ Ser el creador del contenedor (username = '{$container->username}')\n";
echo "2. ✅ Ser miembro del grupo {$container->group_id} con rol: colaborador, administrador, o full_meeting_access\n";
echo "3. ✅ Ser admin de la organización (ID: " . ($container->group->organization->admin_id ?? 'N/A') . ")\n";

echo "\n=== Comandos de Prueba ===\n";
echo "Para probar los permisos de un usuario específico, ejecuta:\n";
echo "php tools/debug_container_permissions.php [user_id]\n";

// Si se proporciona un user_id como argumento
if (isset($argv[1])) {
    $userId = $argv[1];
    echo "\n=== Verificando Usuario ID: {$userId} ===\n";

    $user = User::find($userId);
    if (!$user) {
        echo "❌ Usuario no encontrado\n";
        exit(1);
    }

    echo "Usuario: {$user->full_name} (Username: {$user->username})\n";

    // Verificar si es creador
    $isCreator = $container->username === $user->username;
    echo "¿Es creador?: " . ($isCreator ? '✅ Sí' : '❌ No') . "\n";

    // Verificar si es miembro del grupo
    if ($container->group_id) {
        $groupRole = DB::table('group_user')
            ->where('user_id', $user->id)
            ->where('id_grupo', $container->group_id)
            ->value('rol');

        if ($groupRole) {
            $canAddMeetings = in_array($groupRole, ['colaborador', 'administrador', 'full_meeting_access']);
            echo "Rol en grupo: {$groupRole} " . ($canAddMeetings ? '✅ Puede añadir reuniones' : '❌ No puede añadir reuniones') . "\n";
        } else {
            echo "¿Es miembro del grupo?: ❌ No\n";
        }
    }

    // Verificar si es admin de organización
    if ($container->group && $container->group->organization) {
        $isOrgAdmin = $container->group->organization->admin_id === $user->id;
        echo "¿Es admin de organización?: " . ($isOrgAdmin ? '✅ Sí' : '❌ No') . "\n";
    }

    // Resultado final
    $hasPermission = false;
    if ($isCreator) {
        $hasPermission = true;
        echo "\n🟢 RESULTADO: El usuario TIENE permisos (es creador)\n";
    } elseif ($container->group_id && $groupRole && in_array($groupRole, ['colaborador', 'administrador', 'full_meeting_access'])) {
        $hasPermission = true;
        echo "\n🟢 RESULTADO: El usuario TIENE permisos (miembro del grupo con rol adecuado)\n";
    } elseif ($container->group && $container->group->organization && $container->group->organization->admin_id === $user->id) {
        $hasPermission = true;
        echo "\n🟢 RESULTADO: El usuario TIENE permisos (admin de organización)\n";
    } else {
        echo "\n🔴 RESULTADO: El usuario NO TIENE permisos\n";
    }
}

echo "\n";
