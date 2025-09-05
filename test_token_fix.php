<?php

echo "Testing GoogleToken model...\n";

try {
    $token = App\Models\GoogleToken::first();

    if ($token) {
        echo "Token ID: " . $token->id . "\n";
        echo "Username: " . $token->username . "\n";

        $accessTokenString = $token->getAccessTokenString();
        echo "Access Token String Length: " . strlen($accessTokenString) . "\n";
        echo "Access Token Preview: " . substr($accessTokenString, 0, 50) . "...\n";

        echo "Has Valid Token: " . ($token->hasValidAccessToken() ? 'Yes' : 'No') . "\n";

        // Probar el servicio de actualización
        $service = app(App\Services\GoogleTokenRefreshService::class);
        $status = $service->checkConnectionStatus($token->username);
        echo "Connection Status: \n";
        print_r($status);

    } else {
        echo "No tokens found\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
