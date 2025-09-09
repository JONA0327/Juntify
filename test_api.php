<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

echo "Testing Shared Meetings API Endpoints...\n\n";

// Get all routes that match shared-meetings
$routes = collect(Route::getRoutes())->filter(function ($route) {
    return str_contains($route->uri(), 'shared-meetings');
});

echo "Registered shared-meetings routes:\n";
foreach ($routes as $route) {
    echo "- {$route->methods()[0]} /{$route->uri()}\n";
}

echo "\nTesting basic functionality...\n";

// Test if Contact model exists and has relationships
try {
    $contactCount = \App\Models\Contact::count();
    echo "✓ Contact model works - found {$contactCount} contacts\n";
} catch (\Exception $e) {
    echo "✗ Contact model error: " . $e->getMessage() . "\n";
}

// Test if SharedMeeting model exists
try {
    $sharedCount = \App\Models\SharedMeeting::count();
    echo "✓ SharedMeeting model works - found {$sharedCount} shared meetings\n";
} catch (\Exception $e) {
    echo "✗ SharedMeeting model error: " . $e->getMessage() . "\n";
}

// Test if Notification model exists
try {
    $notificationCount = \App\Models\Notification::count();
    echo "✓ Notification model works - found {$notificationCount} notifications\n";
} catch (\Exception $e) {
    echo "✗ Notification model error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
