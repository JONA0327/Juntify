<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificando todas las reuniones en contenedores de organizaciones ===\n";

// Obtener usuario actual
$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit;
}

echo "✅ Usuario: {$user->username} (ID: {$user->id})\n\n";

// Obtener todas las organizaciones donde el usuario tiene acceso
$organizations = App\Models\Organization::whereHas('groups.users', function($query) use ($user) {
    $query->where('users.id', $user->id);
})->with(['groups' => function($query) use ($user) {
    $query->whereHas('users', function($subQuery) use ($user) {
        $subQuery->where('users.id', $user->id);
    });
}])->get();

echo "✅ Organizaciones accesibles: {$organizations->count()}\n\n";

foreach ($organizations as $org) {
    echo "=== Organización: {$org->name} (ID: {$org->id}) ===\n";

    foreach ($org->groups as $group) {
        echo "  Grupo: {$group->name} (ID: {$group->id})\n";

        // Verificar rol del usuario en este grupo
        $userInGroup = $group->users()->where('users.id', $user->id)->first();
        $userRole = $userInGroup ? $userInGroup->pivot->rol : 'NO_MEMBER';
        echo "    - Rol del usuario: {$userRole}\n";

        // Obtener contenedores del grupo
        $containers = App\Models\MeetingContentContainer::where('group_id', $group->id)->get();
        echo "    - Contenedores: {$containers->count()}\n";

        foreach ($containers as $container) {
            echo "      * Container: {$container->name} (ID: {$container->id})\n";

            // Obtener reuniones en este contenedor
            $meetings = $container->meetings; // Relación a través de MeetingContentRelation
            echo "        - Reuniones: {$meetings->count()}\n";

            foreach ($meetings as $meeting) {
                echo "          > Meeting {$meeting->id}: {$meeting->meeting_name} (Owner: {$meeting->username})\n";
            }
        }
        echo "\n";
    }
}
