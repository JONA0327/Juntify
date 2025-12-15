<?php
require_once(__DIR__ . '/vendor/autoload.php');
$app = require_once(__DIR__ . '/bootstrap/app.php');
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\DB;

// Get google tokens for jona03278
$tokens = DB::table('google_tokens')
    ->where('username', 'Jona0327')
    ->select('username', 'email', 'access_token', 'refresh_token', 'expires_at')
    ->get();

echo "=== Google Tokens for Jona0327 ===\n";
echo "Count: " . $tokens->count() . "\n";

foreach ($tokens as $token) {
    echo "\nToken found:\n";
    echo "  - Username: " . $token->username . "\n";
    echo "  - Email: " . $token->email . "\n";
    echo "  - Has Access Token: " . (!empty($token->access_token) ? "Yes" : "No") . "\n";
    echo "  - Has Refresh Token: " . (!empty($token->refresh_token) ? "Yes" : "No") . "\n";
    echo "  - Expires At: " . $token->expires_at . "\n";
}

if ($tokens->isEmpty()) {
    echo "No Google tokens found. User needs to authenticate with Google Drive.\n";
}
?>
