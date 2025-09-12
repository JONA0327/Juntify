<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Http\Controllers\MeetingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=== Probando acceso directo al método show del controlador ===\n";

// Obtener un usuario que no sea dueño de la reunión pero que esté en el grupo
$testUser = User::where('username', 'Jonalp0327')->first();
$meetingId = 56; // ID de la reunión de prueba

if ($testUser) {
    echo "Usuario de prueba: {$testUser->username} (ID: {$testUser->id})\n";
    echo "Reunión ID: {$meetingId}\n";

    // Simular autenticación
    Auth::login($testUser);

    try {
        // Crear una instancia del controlador
        $controller = app(MeetingController::class);

        // Crear un request mock
        $request = Request::create("/api/meetings/{$meetingId}", 'GET');

        // Llamar al método show
        $response = $controller->show($meetingId);

        // Obtener el contenido de la respuesta
        $responseData = $response->getData(true);

        if (isset($responseData['success']) && $responseData['success']) {
            echo "✅ ÉXITO: El usuario puede acceder a la reunión\n";
            echo "Nombre de reunión: " . $responseData['meeting']['meeting_name'] . "\n";
        } else {
            echo "❌ ERROR: El usuario no puede acceder a la reunión\n";
            if (isset($responseData['message'])) {
                echo "Mensaje: " . $responseData['message'] . "\n";
            }
        }

    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'No query results for model') !== false) {
            echo "❌ ERROR: No query results for model - El problema persiste\n";
        } else {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "❌ Usuario de prueba no encontrado\n";
}
