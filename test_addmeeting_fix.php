<?php

require_once 'bootstrap/app.php';

use Illuminate\Http\Request;
use App\Http\Controllers\ContainerController;
use App\Models\User;
use App\Models\Container;
use App\Models\TranscriptionLaravel;

try {
    // Buscar un usuario existente
    $user = User::first();
    if (!$user) {
        echo "❌ No se encontró ningún usuario\n";
        exit(1);
    }
    echo "✅ Usuario encontrado: {$user->username}\n";

    // Buscar un contenedor del usuario
    $container = Container::where('created_by', $user->id)->first();
    if (!$container) {
        echo "❌ No se encontró ningún contenedor del usuario\n";
        exit(1);
    }
    echo "✅ Contenedor encontrado: {$container->nombre}\n";

    // Buscar una reunión del usuario
    $meeting = TranscriptionLaravel::where('username', $user->username)->first();
    if (!$meeting) {
        echo "❌ No se encontró ninguna reunión del usuario\n";
        exit(1);
    }
    echo "✅ Reunión encontrada: {$meeting->meeting_name}\n";

    // Simular request para agregar reunión al contenedor
    $request = Request::create('/api/content-containers/add-meeting', 'POST', [
        'container_id' => $container->id,
        'meeting_id' => $meeting->id
    ]);

    // Autenticar el usuario en la aplicación
    auth()->login($user);

    // Crear controlador y probar el método
    $controller = new ContainerController();
    $response = $controller->addMeeting($request);

    if ($response->status() === 200) {
        echo "✅ Reunión agregada exitosamente al contenedor\n";
        echo "📝 Respuesta: " . $response->getContent() . "\n";
    } else {
        echo "❌ Error al agregar reunión. Status: " . $response->status() . "\n";
        echo "📝 Respuesta: " . $response->getContent() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
