<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\User;
use App\Models\Container;

try {
    $user = User::first();
    if (!$user) {
        echo "No se encontraron usuarios\n";
        exit(1);
    }

    $username = $user->username;
    echo "Creando contenedores para usuario: $username\n";

    $containers = [
        [
            'name' => 'Reuniones de Equipo',
            'description' => 'Contenedor para reuniones semanales del equipo',
            'username' => $username,
            'is_active' => true,
        ],
        [
            'name' => 'Reuniones de Proyecto',
            'description' => 'Contenedor para reuniones del proyecto principal',
            'username' => $username,
            'is_active' => true,
        ],
        [
            'name' => 'Reuniones Cliente',
            'description' => 'Contenedor para reuniones con clientes',
            'username' => $username,
            'is_active' => true,
        ],
    ];

    foreach ($containers as $containerData) {
        $exists = Container::where('name', $containerData['name'])
            ->where('username', $username)
            ->exists();

        if (!$exists) {
            Container::create($containerData);
            echo "Creado contenedor: {$containerData['name']}\n";
        } else {
            echo "Ya existe contenedor: {$containerData['name']}\n";
        }
    }

    echo "Proceso completado\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
