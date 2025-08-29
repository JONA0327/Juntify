<?php

// Archivo temporal para debug - eliminar después de usar
require_once __DIR__ . '/vendor/autoload.php';

// Cargar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "=== DEBUG ORGANIZACIONES ===\n\n";

// Ver todas las organizaciones
$organizations = App\Models\Organization::with('groups.users')->get();
echo "Organizaciones en DB: " . $organizations->count() . "\n\n";

foreach ($organizations as $org) {
    echo "Organización: " . $org->nombre_organizacion . " (ID: " . $org->id . ")\n";
    echo "Admin ID: " . $org->admin_id . "\n";
    echo "Grupos: " . $org->groups->count() . "\n";

    foreach ($org->groups as $group) {
        echo "  - Grupo: " . $group->nombre_grupo . " (ID: " . $group->id . ")\n";
        echo "    Usuarios en grupo: " . $group->users->count() . "\n";
        foreach ($group->users as $user) {
            echo "      * " . $user->email . " (rol: " . $user->pivot->rol . ")\n";
        }
    }
    echo "\n";
}

// Ver relaciones en tabla pivot
echo "=== RELACIONES GRUPO-USUARIO ===\n";
$relations = DB::table('group_user')->get();
echo "Relaciones en group_user: " . $relations->count() . "\n";

// Ver estructura de la primera relación
if ($relations->count() > 0) {
    echo "Estructura de la tabla pivot:\n";
    $first = $relations->first();
    foreach ($first as $key => $value) {
        echo "  $key: $value\n";
    }
    echo "\n";
}

foreach ($relations as $rel) {
    $user = App\Models\User::find($rel->user_id);
    $group = App\Models\Group::find($rel->id_grupo); // Usar id_grupo en lugar de group_id
    echo "Usuario: " . ($user ? $user->email : 'NO ENCONTRADO') . " -> Grupo: " . ($group ? $group->nombre_grupo : 'NO ENCONTRADO') . " (rol: " . $rel->rol . ")\n";
}

echo "\n=== TESTEAR QUERY DEL CONTROLADOR ===\n";
// Usar un usuario que sabemos que está en un grupo
$testUser = App\Models\User::where('email', 'jona03278@gmail.com')->first();
if ($testUser) {
    echo "Testeando con usuario: " . $testUser->email . " (rol: " . $testUser->roles . ")\n";

    // Query del controlador
    $userOrgs = App\Models\Organization::whereHas('groups', function ($query) use ($testUser) {
        $query->whereHas('users', function ($subQuery) use ($testUser) {
            $subQuery->where('users.id', $testUser->id);
        });
    })->with([
        'groups' => function ($query) use ($testUser) {
            $query->whereHas('users', function ($subQuery) use ($testUser) {
                $subQuery->where('users.id', $testUser->id);
            })->with(['users', 'code']);
        }
    ])->get();

    echo "Organizaciones encontradas para este usuario: " . $userOrgs->count() . "\n";

    foreach ($userOrgs as $org) {
        echo "  - " . $org->nombre_organizacion . " (grupos: " . $org->groups->count() . ")\n";
    }
} else {
    echo "Usuario jona03278@gmail.com no encontrado\n";
}
