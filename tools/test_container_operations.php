<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Configurar la aplicación Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\ContainerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\TranscriptionLaravel;

echo "=== TESTING ADD MEETING TO CONTAINER ===\n";

// Simular un usuario autenticado
$user = User::where('username', 'Jona0327')->first();
if (!$user) {
    echo "❌ Usuario Jona0327 no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$user->full_name} (ID: {$user->id})\n";

// Simular autenticación
Auth::login($user);

// Buscar una reunión del usuario para probar
$meeting = TranscriptionLaravel::where('username', $user->username)->first();
if (!$meeting) {
    echo "❌ No se encontraron reuniones para el usuario\n";
    exit(1);
}

echo "✅ Reunión encontrada: {$meeting->meeting_name} (ID: {$meeting->id})\n";

// Crear un request simulado
$request = new Request();
$request->merge([
    'meeting_id' => $meeting->id
]);

echo "📋 Request data: " . json_encode($request->all()) . "\n";

try {
    $controller = new ContainerController(app(\App\Services\GoogleDriveService::class));
    echo "✅ Controller creado\n";

    // Usar el contenedor ID 5 que está en los errores
    $containerId = 5;
    echo "🎯 Probando con contenedor ID: {$containerId}\n";

    $response = $controller->addMeeting($request, $containerId);
    echo "✅ Response: " . $response->getContent() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TESTING DELETE CONTAINER ===\n";

try {
    // Probar eliminar el contenedor 6 que está en los errores
    $containerId = 6;
    echo "🎯 Probando eliminar contenedor ID: {$containerId}\n";

    $response = $controller->destroy($containerId);
    echo "✅ Delete Response: " . $response->getContent() . "\n";

} catch (Exception $e) {
    echo "❌ Delete Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";
