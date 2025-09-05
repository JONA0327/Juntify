<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificación de campos separados en la BD ===\n\n";

try {
    // Obtener un token recién actualizado
    $token = App\Models\GoogleToken::orderBy('updated_at', 'desc')->first();

    if ($token) {
        echo "--- Token más reciente ---\n";
        echo "ID: " . $token->id . "\n";
        echo "Username: " . $token->username . "\n";
        echo "Updated: " . $token->updated_at . "\n\n";

        echo "--- Campos separados (nuevos) ---\n";
        echo "expires_in: " . ($token->expires_in ?? 'NULL') . "\n";
        echo "scope: " . ($token->scope ?? 'NULL') . "\n";
        echo "token_type: " . ($token->token_type ?? 'NULL') . "\n";
        echo "id_token: " . ($token->id_token ? substr($token->id_token, 0, 50) . '...' : 'NULL') . "\n";
        echo "token_created_at: " . ($token->token_created_at ?? 'NULL') . "\n\n";

        echo "--- Campos legacy ---\n";
        echo "access_token (directo): " . substr($token->getAttributes()['access_token'], 0, 50) . "...\n";
        echo "refresh_token: " . substr($token->refresh_token, 0, 30) . "...\n";
        echo "expiry_date: " . $token->expiry_date . "\n\n";

        echo "--- Métodos del modelo ---\n";
        echo "getAccessTokenString(): " . substr($token->getAccessTokenString(), 0, 50) . "...\n";
        echo "hasValidAccessToken(): " . ($token->hasValidAccessToken() ? 'true' : 'false') . "\n";

        $tokenArray = $token->getTokenArray();
        echo "getTokenArray() keys: " . implode(', ', array_keys($tokenArray)) . "\n";

    } else {
        echo "No se encontraron tokens en la base de datos.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
