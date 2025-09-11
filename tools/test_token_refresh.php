<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\GoogleTokenRefreshService;

echo "=== Testing Token Refresh ===\n";

$sharer = User::where('email', 'fanlee1996@gmail.com')->first();
if (!$sharer || !$sharer->googleToken) {
    echo "No sharer or token found\n";
    exit;
}

$refreshService = app(GoogleTokenRefreshService::class);

echo "Before refresh:\n";
echo "- Token updated: " . $sharer->googleToken->updated_at . "\n";

try {
    $result = $refreshService->refreshTokenForUser($sharer);
    echo "Token refresh result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

    // Reload the user to get updated token
    $sharer->refresh();
    echo "After refresh:\n";
    echo "- Token updated: " . $sharer->googleToken->updated_at . "\n";

} catch (\Throwable $e) {
    echo "Token refresh error: " . $e->getMessage() . "\n";
}
