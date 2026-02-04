<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== RUTAS DE MEETINGS REGISTRADAS ===\n\n";

$routes = \Illuminate\Support\Facades\Route::getRoutes();

foreach ($routes as $route) {
    $uri = $route->uri();
    if (str_contains($uri, 'meetings')) {
        echo "URI: {$uri}\n";
        echo "Métodos: " . implode(', ', $route->methods()) . "\n";
        echo "Acción: " . $route->getActionName() . "\n";
        echo str_repeat('-', 60) . "\n";
    }
}
