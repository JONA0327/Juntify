<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\GroupCode;
use App\Http\Controllers\GroupController;

echo "=== TESTING REAL JOIN BY CODE FLOW ===\n\n";

// 1. Buscar un código de grupo existente
$groupCode = GroupCode::with('group.organization')->first();

if (!$groupCode) {
    echo "❌ No se encontró un código de grupo para la prueba\n";
    exit;
}

echo "🎫 Código de grupo encontrado: {$groupCode->code}\n";
echo "🏷️ Grupo: {$groupCode->group->nombre_grupo}\n";
echo "🏢 Organización: ID {$groupCode->group->id_organizacion}\n\n";

// 2. Buscar un usuario que no esté en ninguna organización
$userWithoutOrg = User::whereDoesntHave('organizations')
    ->whereNull('current_organization_id')
    ->orWhere('current_organization_id', '')
    ->where('id', '!=', 'tc5426244@gmail.com') // Excluir el usuario que ya usamos
    ->first();

if (!$userWithoutOrg) {
    echo "❌ No se encontró un usuario disponible para la prueba\n";
    exit;
}

echo "👤 Usuario de prueba: {$userWithoutOrg->full_name} ({$userWithoutOrg->email})\n";
echo "📋 current_organization_id ANTES: '{$userWithoutOrg->current_organization_id}'\n\n";

// 3. Simular la autenticación y usar el controlador real
echo "=== USANDO CONTROLADOR REAL ===\n";

try {
    // Simular autenticación
    auth()->login($userWithoutOrg);

    // Crear instancia del controlador
    $controller = new GroupController();

    // Crear una request simulada
    $request = new \Illuminate\Http\Request();
    $request->merge(['code' => $groupCode->code]);

    // Ejecutar el método joinByCode
    $response = $controller->joinByCode($request);

    if ($response->getStatusCode() === 200) {
        echo "✅ joinByCode ejecutado exitosamente\n";

        // Verificar que se actualizó el current_organization_id
        $updatedUser = User::find($userWithoutOrg->id);
        echo "📋 current_organization_id DESPUÉS: '{$updatedUser->current_organization_id}'\n";

        if ($updatedUser->current_organization_id == $groupCode->group->id_organizacion) {
            echo "🎉 ¡PERFECT! El current_organization_id se actualizó automáticamente\n";
        } else {
            echo "❌ ERROR: El current_organization_id NO se actualizó correctamente\n";
            echo "   Esperado: {$groupCode->group->id_organizacion}\n";
            echo "   Actual: '{$updatedUser->current_organization_id}'\n";
        }

        // Mostrar la respuesta del controlador
        $responseData = json_decode($response->getContent(), true);
        echo "\n📄 Respuesta del controlador:\n";
        echo "   - Organización: " . ($responseData['organization']['nombre_organizacion'] ?? 'N/A') . "\n";
        echo "   - Grupo: " . ($responseData['group']['nombre_grupo'] ?? 'N/A') . "\n";

    } else {
        echo "❌ Error en joinByCode: " . $response->getStatusCode() . "\n";
        echo "Contenido: " . $response->getContent() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Excepción durante la prueba: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA REAL ===\n";
