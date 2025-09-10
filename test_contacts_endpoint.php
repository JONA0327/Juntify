<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SharedMeetingController;

echo "=== PRUEBA DEL ENDPOINT DE CONTACTOS ===\n\n";

try {
    // Simular usuario autenticado
    $user = App\Models\User::first();
    if (!$user) {
        echo "No hay usuarios en la base de datos\n";
        exit;
    }

    Auth::loginUsingId($user->id);
    echo "Usuario autenticado: " . ($user->full_name ?? $user->username) . " (ID: {$user->id})\n\n";

    // Probar el controlador
    $controller = new SharedMeetingController();
    $response = $controller->getContactsForSharing();

    echo "Código de respuesta: " . $response->getStatusCode() . "\n";
    echo "Contenido:\n";
    $content = json_decode($response->getContent(), true);
    print_r($content);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
