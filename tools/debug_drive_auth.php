<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\GoogleServiceAccount;
use App\Services\GoogleDriveService;

echo "=== Debugging Drive Authentication ===\n";

// Check the sharer user token
$sharer = User::where('email', 'fanlee1996@gmail.com')->first();
if ($sharer && $sharer->googleToken) {
    echo "Sharer has Google token: YES\n";
    echo "Token type: " . gettype($sharer->googleToken) . "\n";
    if (is_object($sharer->googleToken)) {
        echo "Access token present: " . (!empty($sharer->googleToken->access_token) ? 'YES' : 'NO') . "\n";
        echo "Refresh token present: " . (!empty($sharer->googleToken->refresh_token) ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "Sharer has Google token: NO\n";
}

// Test Service Account
echo "\n=== Testing Service Account ===\n";
try {
    $sa = app(GoogleServiceAccount::class);
    echo "Service Account instantiated: YES\n";

    // Try to impersonate the sharer
    if ($sharer && $sharer->email) {
        try {
            $sa->impersonate($sharer->email);
            echo "Impersonation successful: YES\n";
        } catch (\Throwable $e) {
            echo "Impersonation failed: " . $e->getMessage() . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "Service Account setup failed: " . $e->getMessage() . "\n";
}

// Test user token Drive service
echo "\n=== Testing User Token Drive Service ===\n";
if ($sharer && $sharer->googleToken) {
    try {
        $drive = app(GoogleDriveService::class);
        $token = $sharer->googleToken;
        $accessToken = $token->access_token ? json_decode($token->access_token, true) ?: ['access_token' => $token->access_token] : [];
        $drive->setAccessToken($accessToken);
        echo "User token Drive service setup: YES\n";
    } catch (\Throwable $e) {
        echo "User token Drive service failed: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Environment Check ===\n";
echo "Service Account Key File: " . (file_exists(config('services.google.service_account_key_file')) ? 'EXISTS' : 'MISSING') . "\n";
echo "Google API Key: " . (config('services.google.api_key') ? 'SET' : 'NOT SET') . "\n";
