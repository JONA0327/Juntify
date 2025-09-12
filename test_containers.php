<?php

require_once 'vendor/autoload.php';

use App\Models\Container;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

try {
    echo "Testing Container model...\n";

    // Test database connection
    $user = User::first();
    if (!$user) {
        echo "No users found\n";
        exit(1);
    }

    echo "User found: " . $user->username . "\n";

    // Test containers query
    $containers = Container::where('username', $user->username)->get();
    echo "Containers found: " . $containers->count() . "\n";

    foreach ($containers as $container) {
        echo "- Container: " . $container->name . "\n";
    }

    echo "Test completed successfully\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
