<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Simulando llamada API para subcarpetas de organización ===\n";

$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
$org = App\Models\Organization::find($user->current_organization_id);

if ($user && $org) {
    echo "✅ Usuario: {$user->username}\n";
    echo "✅ Organización actual: {$org->nombre_organizacion} (ID: {$org->id})\n";

    // Simular el endpoint /api/organizations/{id}/drive/subfolders
    $controller = new App\Http\Controllers\OrganizationDriveController(app(App\Services\GoogleDriveService::class));

    // Simular autenticación
    auth()->login($user);

    try {
        $response = $controller->listSubfolders($org);
        $data = json_decode($response->getContent(), true);

        echo "✅ API Response Status: {$response->getStatusCode()}\n";
        echo "✅ Root folder: {$data['root_folder']['name']} (Google ID: {$data['root_folder']['google_id']})\n";
        echo "📁 Subfolders ({$data['root_folder']['name']}):\n";

        foreach ($data['subfolders'] as $subfolder) {
            echo "  - {$subfolder['name']} (Google ID: {$subfolder['google_id']})\n";
        }

    } catch (Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
    }

} else {
    echo "❌ Usuario o organización no encontrados\n";
}
