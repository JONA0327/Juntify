<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Análisis de tokens en la base de datos ===\n\n";

try {
    // Obtener algunos tokens de ejemplo
    $tokens = App\Models\GoogleToken::take(5)->get();

    echo "Total de tokens en la BD: " . App\Models\GoogleToken::count() . "\n\n";

    if ($tokens->count() > 0) {
        foreach ($tokens as $index => $token) {
            echo "--- Token #" . ($index + 1) . " ---\n";
            echo "ID: " . $token->id . "\n";
            echo "Username: " . $token->username . "\n";
            echo "Created: " . $token->created_at . "\n";
            echo "Updated: " . $token->updated_at . "\n";

            // Mostrar el access_token RAW (como está en la BD)
            $rawToken = $token->getAttributes()['access_token'];
            echo "Access Token RAW (como está en BD):\n";
            echo "Tipo: " . gettype($rawToken) . "\n";
            echo "Longitud: " . strlen($rawToken) . " caracteres\n";

            // Verificar si es JSON
            $decoded = json_decode($rawToken, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "Formato: JSON válido\n";
                echo "Estructura JSON:\n";
                if (is_array($decoded)) {
                    foreach ($decoded as $key => $value) {
                        if ($key === 'access_token') {
                            echo "  - $key: " . substr($value, 0, 50) . "... (longitud: " . strlen($value) . ")\n";
                        } elseif ($key === 'refresh_token') {
                            echo "  - $key: " . substr($value, 0, 30) . "... (longitud: " . strlen($value) . ")\n";
                        } else {
                            echo "  - $key: $value\n";
                        }
                    }
                }
            } else {
                echo "Formato: String simple (no JSON)\n";
                echo "Preview: " . substr($rawToken, 0, 50) . "...\n";
            }

            // Probar los nuevos métodos del modelo
            echo "\n--- Métodos del modelo ---\n";
            echo "getAccessTokenString(): " . substr($token->getAccessTokenString() ?? 'NULL', 0, 50) . "...\n";
            echo "hasValidAccessToken(): " . ($token->hasValidAccessToken() ? 'true' : 'false') . "\n";

            // Mostrar el refresh_token también
            echo "Refresh Token: " . substr($token->refresh_token ?? 'NULL', 0, 30) . "...\n";
            echo "Expiry Date: " . $token->expiry_date . "\n";

            echo "\n" . str_repeat("-", 50) . "\n\n";
        }
    } else {
        echo "No se encontraron tokens en la base de datos.\n";
    }

    // Verificar la estructura de la tabla
    echo "=== Estructura de la tabla google_tokens ===\n";
    $columns = \Illuminate\Support\Facades\DB::select("DESCRIBE google_tokens");
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type} (Null: {$column->Null}, Key: {$column->Key})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
