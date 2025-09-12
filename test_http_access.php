<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Probando acceso via HTTP simulation ===\n";

// Primero obtener el token del usuario para simular autenticación
$testUser = User::where('username', 'Jonalp0327')->first();

if ($testUser) {
    echo "Usuario: {$testUser->username}\n";

    // Crear token personal para API
    $token = $testUser->createToken('test')->plainTextToken;
    echo "Token creado: " . substr($token, 0, 20) . "...\n";

    // Hacer petición HTTP real usando cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/meetings/56");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        "Accept: application/json",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: {$httpCode}\n";

    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success']) {
            echo "✅ ÉXITO: Acceso permitido\n";
            echo "Reunión: " . $data['meeting']['meeting_name'] . "\n";
        } else {
            echo "❌ ERROR: " . ($data['message'] ?? 'Error desconocido') . "\n";
        }
    } else {
        echo "❌ No response\n";
    }

    // Limpiar token
    $testUser->tokens()->delete();

} else {
    echo "❌ Usuario no encontrado\n";
}
