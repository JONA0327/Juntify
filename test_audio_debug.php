<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

try {
    // Simular usuario autenticado
    $user = App\Models\User::where('username', 'Jonalp0327')->first();
    if (!$user) {
        echo "Usuario Jonalp0327 no encontrado\n";
        exit(1);
    }

    Auth::login($user);
    echo "Usuario autenticado: " . $user->username . "\n";

    // Crear instancia del controlador
    $controller = new App\Http\Controllers\MeetingController();

    echo "Probando streamAudio para meeting 56...\n";

    $response = $controller->streamAudio(56);

    echo "Tipo de respuesta: " . get_class($response) . "\n";
    echo "Status code: " . $response->getStatusCode() . "\n";

    if (method_exists($response, 'getContent')) {
        $content = $response->getContent();
        if (strlen($content) > 200) {
            echo "Contenido: " . substr($content, 0, 200) . "...\n";
        } else {
            echo "Contenido: " . $content . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
