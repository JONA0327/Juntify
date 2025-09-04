<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Organization Drive Status...\n";

try {
    $org = App\Models\Organization::find(12);
    if (!$org) {
        echo "Organization 12 not found\n";
        exit(1);
    }

    echo "Organization found: " . $org->nombre_organizacion . "\n";

    $token = $org->googleToken;
    if (!$token) {
        echo "No Google token found for organization\n";
    } else {
        echo "Google token found: " . ($token->isConnected() ? "connected" : "not connected") . "\n";
    }

    $controller = new App\Http\Controllers\OrganizationDriveController(
        new App\Services\GoogleDriveService()
    );

    // Mock auth user for testing
    $user = App\Models\User::where('email', 'jona03278@gmail.com')->first();
    if (!$user) {
        echo "User jona03278@gmail.com not found\n";
        exit(1);
    }

    echo "Using user: " . $user->email . "\n";
    Illuminate\Support\Facades\Auth::login($user);

    echo "Calling status method...\n";
    $result = $controller->status($org);
    echo "Status result: " . $result->getContent() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
