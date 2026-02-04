<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Probando CompanyMembersController directamente...\n\n";

use App\Http\Controllers\Api\CompanyMembersController;
use Illuminate\Http\Request;

$controller = new CompanyMembersController();

// Crear un request mock
$request = Request::create('/api/companies/2/members', 'GET', ['include_owner' => 'true']);

try {
    $response = $controller->getMembers($request, 2);
    
    echo "Status Code: " . $response->status() . "\n";
    echo "Content:\n";
    echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
    echo "\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
