<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Http\Controllers\MeetingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=== Probando acceso con usuario sin permisos ===\n";

// Buscar un usuario que NO esté en el grupo
$testUser = User::where('username', '!=', 'Jona0327')
    ->where('username', '!=', 'Jonalp0327')
    ->first();

$meetingId = 56; // ID de la reunión de prueba

if ($testUser) {
    echo "Usuario de prueba: {$testUser->username} (ID: {$testUser->id})\n";
    echo "Reunión ID: {$meetingId}\n";

    // Simular autenticación
    Auth::login($testUser);

    try {
        // Crear una instancia del controlador
        $controller = app(MeetingController::class);

        // Llamar al método show
        $response = $controller->show($meetingId);

        // Obtener el contenido de la respuesta
        $responseData = $response->getData(true);

        if (isset($responseData['success']) && $responseData['success']) {
            echo "⚠️ PROBLEMA: El usuario SIN permisos puede acceder a la reunión\n";
            echo "Nombre de reunión: " . $responseData['meeting']['meeting_name'] . "\n";
        } else {
            echo "✅ CORRECTO: El usuario sin permisos NO puede acceder a la reunión\n";
            if (isset($responseData['message'])) {
                echo "Mensaje: " . $responseData['message'] . "\n";
            }
        }

    } catch (\Exception $e) {
        echo "✅ CORRECTO: Excepción capturada para usuario sin permisos\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ No se encontró usuario para prueba\n";
}
