<?php

use Illuminate\Support\Facades\DB;

// Obtener el username del primer usuario
$user = DB::table('users')->first();
if (!$user) {
    echo "No se encontraron usuarios\n";
    exit(1);
}

$username = $user->username;
echo "Creando contenedores para usuario: $username\n";

// Crear algunos contenedores de prueba
$containers = [
    [
        'name' => 'Reuniones de Equipo',
        'description' => 'Contenedor para reuniones semanales del equipo',
        'username' => $username,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Reuniones de Proyecto',
        'description' => 'Contenedor para reuniones del proyecto principal',
        'username' => $username,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'name' => 'Reuniones Cliente',
        'description' => 'Contenedor para reuniones con clientes',
        'username' => $username,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ],
];

foreach ($containers as $container) {
    // Verificar si ya existe
    $exists = DB::table('meeting_content_containers')
        ->where('name', $container['name'])
        ->where('username', $username)
        ->exists();

    if (!$exists) {
        DB::table('meeting_content_containers')->insert($container);
        echo "Creado contenedor: {$container['name']}\n";
    } else {
        echo "Ya existe contenedor: {$container['name']}\n";
    }
}

echo "Creación de contenedores completada\n";
