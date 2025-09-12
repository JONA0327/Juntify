<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificando contexto de contenedor para reunión 56 ===\n";

// 1. Verificar si la reunión está en algún contenedor
$containerMeetings = App\Models\MeetingContentRelation::where('meeting_id', 56)->get();
if ($containerMeetings->count() > 0) {
    echo "✅ La reunión está en contenedores:\n";
    foreach ($containerMeetings as $cm) {
        echo "  - Container ID: {$cm->container_id}\n";

        // Verificar el contenedor
        $container = App\Models\MeetingContentContainer::find($cm->container_id);
        if ($container) {
            echo "    - Container Name: {$container->name}\n";
            echo "    - Container Group ID: {$container->group_id}\n";

            // Verificar el grupo
            $group = App\Models\Group::find($container->group_id);
            if ($group) {
                echo "      - Group Name: {$group->name}\n";
                echo "      - Organization ID: {$group->organization_id}\n";

                // Verificar si el usuario está en este grupo
                $user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
                if ($user) {
                    $userInGroup = $group->users()->where('users.id', $user->id)->exists();
                    echo "      - ¿Usuario en grupo?: " . ($userInGroup ? 'SÍ' : 'NO') . "\n";

                    if ($userInGroup) {
                        $userGroupRole = $group->users()->where('users.id', $user->id)->first()->pivot->rol;
                        echo "      - Rol en grupo: {$userGroupRole}\n";
                    }
                }
            }
        }
    }
} else {
    echo "❌ La reunión NO está en ningún contenedor\n";
}

// 2. También verificar si hay alguna referencia en transcriptions_laravel
$legacyRef = App\Models\TranscriptionLaravel::where('id', 56)->first();
if ($legacyRef) {
    echo "\n✅ Referencia en transcriptions_laravel:\n";
    echo "  - Username: {$legacyRef->username}\n";
    echo "  - Meeting name: {$legacyRef->meeting_name}\n";

    // Verificar si está en contenedores desde legacy
    $legacyContainers = $legacyRef->containers;
    if ($legacyContainers->count() > 0) {
        echo "  - ✅ Está en contenedores desde tabla legacy\n";
        foreach ($legacyContainers as $container) {
            echo "    - Container: {$container->name} (ID: {$container->id})\n";
        }
    }
}
