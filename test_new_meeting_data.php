<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificando datos que se pasan a new-meeting ===\n";

$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();

if ($user) {
    // Simular autenticación
    auth()->login($user);

    echo "✅ Usuario: {$user->username}\n";
    echo "✅ Current org ID: " . ($user->current_organization_id ?: 'NULL') . "\n";
    echo "✅ User role: " . ($user->roles ?? 'free') . "\n";

    // Simular los datos que se pasan a la vista new-meeting
    $viewData = [
        'userRole' => $user->roles ?? 'free',
        'organizationId' => $user->current_organization_id ?? null
    ];

    echo "\n📄 Datos para la vista new-meeting:\n";
    echo "  - userRole: {$viewData['userRole']}\n";
    echo "  - organizationId: " . ($viewData['organizationId'] ?: 'NULL') . "\n";

    // JavaScript que se generaría
    $jsOrganizationId = json_encode($viewData['organizationId']);
    echo "\n🟨 JavaScript generado:\n";
    echo "window.currentOrganizationId = {$jsOrganizationId};\n";

} else {
    echo "❌ Usuario no encontrado\n";
}
