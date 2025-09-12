<?php

// Script para probar el acceso al audio de reuniones a través de contenedores
require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simular request
$request = Request::create('/api/meetings/56/audio', 'GET');
$response = $kernel->handle($request);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";

if ($response->isRedirect()) {
    echo "Redirect to: " . $response->headers->get('Location') . "\n";
}

$kernel->terminate($request, $response);
