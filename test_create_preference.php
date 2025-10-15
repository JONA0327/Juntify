<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simular una request POST al endpoint
$request = Illuminate\Http\Request::create('/subscription/create-preference', 'POST', [
    'plan_code' => 'basico'
]);

// Simular que hay un usuario autenticado
$user = App\Models\User::first();
if ($user) {
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
}

try {
    $response = $kernel->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Response: " . $response->getContent() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response ?? null);
