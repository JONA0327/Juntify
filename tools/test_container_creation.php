<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Configurar la aplicación Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\ContainerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "=== TESTING CONTAINER CREATION ===\n";

// Simular un usuario autenticado
$user = User::where('username', 'Jona0327')->first();
if (!$user) {
    echo "❌ Usuario Jona0327 no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$user->full_name} (ID: {$user->id})\n";

// Simular autenticación
Auth::login($user);

echo "✅ Usuario autenticado\n";

// Crear un request simulado para contenedor personal (sin group_id)
$request = new Request();
$request->merge([
    'name' => 'Test Container Personal',
    'description' => 'Container de prueba personal'
    // No incluimos group_id para simular contenedor personal
]);

echo "📋 Request data: " . json_encode($request->all()) . "\n";

try {
    $controller = new ContainerController(app(\App\Services\GoogleDriveService::class));
    echo "✅ Controller creado\n";

    $response = $controller->store($request);
    echo "✅ Response: " . $response->getContent() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TESTING CONTAINER WITH UNDEFINED GROUP_ID ===\n";

// Simular request con group_id undefined (como viene del JavaScript)
$request2 = new Request();
$request2->merge([
    'name' => 'Test Container Undefined Group',
    'description' => 'Container con group_id undefined',
    'group_id' => null  // Simular undefined del JavaScript
]);

echo "📋 Request data: " . json_encode($request2->all()) . "\n";

try {
    $response2 = $controller->store($request2);
    echo "✅ Response: " . $response2->getContent() . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";
