<?php

require_once 'vendor/autoload.php';

use App\Models\Organization;
use App\Services\GoogleDriveService;

// Configurar el entorno Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Prueba de validación de token de organización ===\n\n";

try {
    // Obtener organización con token
    $org = Organization::whereHas('googleToken')->first();

    if (!$org || !$org->googleToken) {
        echo "❌ No hay organización con token\n";
        exit(1);
    }

    echo "✅ Organización: {$org->name}\n";
    $token = $org->googleToken;

    echo "📋 Información del token:\n";
    echo "   - isConnected(): " . ($token->isConnected() ? 'Sí' : 'No') . "\n";
    echo "   - isExpired(): " . ($token->isExpired() ? 'Sí' : 'No') . "\n";
    echo "   - expiry_date: " . ($token->expiry_date ?? 'NULL') . "\n";
    echo "   - expires_at: " . ($token->expires_at ?? 'NULL') . "\n";

    // Verificar el contenido del token
    $tokenData = $token->access_token;
    if (is_array($tokenData)) {
        echo "   - Token es array con " . count($tokenData) . " elementos\n";
        if (isset($tokenData['expires_in'])) {
            echo "   - expires_in: {$tokenData['expires_in']}\n";
        }
        if (isset($tokenData['created'])) {
            echo "   - created: {$tokenData['created']}\n";
        }
    } else {
        echo "   - Token es string de " . strlen($tokenData) . " caracteres\n";
    }

    // Intentar usar el token
    echo "\n🔄 Probando token con GoogleDriveService...\n";
    $driveService = new GoogleDriveService();

    try {
        $client = $driveService->getClient();
        $client->setAccessToken($tokenData);

        // Intentar una operación simple
        $driveService->listFolders();
        echo "✅ Token FUNCIONA correctamente\n";

    } catch (\Exception $e) {
        echo "❌ Token FALLA: {$e->getMessage()}\n";

        // Verificar si es error de autenticación
        if (strpos($e->getMessage(), '401') !== false ||
            strpos($e->getMessage(), 'Invalid Credentials') !== false ||
            strpos($e->getMessage(), 'UNAUTHENTICATED') !== false) {
            echo "🔍 Análisis: Token expirado o inválido\n";

            // Verificar si el token se puede refrescar
            if ($token->refresh_token) {
                echo "🔧 Token de refresco disponible, intentando refrescar...\n";
                try {
                    $client->refreshToken($token->refresh_token);
                    $newToken = $client->getAccessToken();
                    echo "✅ Token refrescado exitosamente\n";
                    echo "   Nuevo token expira en: " . ($newToken['expires_in'] ?? 'N/A') . " segundos\n";

                    // Actualizar en base de datos
                    $token->access_token = $newToken;
                    if (isset($newToken['created'])) {
                        $token->expiry_date = now()->addSeconds($newToken['expires_in']);
                    }
                    $token->save();
                    echo "✅ Token actualizado en base de datos\n";

                } catch (\Exception $refreshError) {
                    echo "❌ No se pudo refrescar el token: {$refreshError->getMessage()}\n";
                }
            } else {
                echo "❌ No hay token de refresco disponible\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n=== Prueba completada ===\n";
