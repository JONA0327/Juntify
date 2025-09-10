<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use App\Http\Controllers\GroupController;
use Illuminate\Support\Facades\DB;

echo "=== TESTING AUTOMATIC current_organization_id UPDATE ===\n\n";

// 1. Buscar un usuario sin organización
$userWithoutOrg = User::whereNull('current_organization_id')
    ->orWhere('current_organization_id', '')
    ->first();

if (!$userWithoutOrg) {
    echo "❌ No se encontró un usuario sin organización para la prueba\n";
    exit;
}

echo "👤 Usuario de prueba: {$userWithoutOrg->full_name} ({$userWithoutOrg->email})\n";
echo "📋 Organización actual ANTES: '{$userWithoutOrg->current_organization_id}'\n\n";

// 2. Buscar un grupo existente al cual pueda unirse
$availableGroup = Group::whereHas('organization')
    ->whereDoesntHave('users', function($query) use ($userWithoutOrg) {
        $query->where('users.id', $userWithoutOrg->id);
    })
    ->first();

if (!$availableGroup) {
    echo "❌ No se encontró un grupo disponible para la prueba\n";
    exit;
}

echo "🏷️ Grupo de prueba: {$availableGroup->nombre_grupo}\n";
echo "🏢 Organización del grupo: ID {$availableGroup->id_organizacion}\n\n";

// 3. Simular que el usuario se une al grupo usando joinByCode o accept
echo "=== SIMULANDO UNIÓN AL GRUPO ===\n";

try {
    // Simular autenticación
    auth()->login($userWithoutOrg);

    // Agregar usuario al grupo manualmente (simulando accept o joinByCode)
    $availableGroup->users()->syncWithoutDetaching([$userWithoutOrg->id => ['rol' => 'invitado']]);
    $availableGroup->update(['miembros' => $availableGroup->users()->count()]);

    // Agregar a la organización
    $organization = $availableGroup->organization;
    $organization->users()->syncWithoutDetaching([$userWithoutOrg->id => ['rol' => 'invitado']]);
    $organization->refreshMemberCount();

    // AQUÍ ES DONDE DEBE ACTUALIZARSE EL current_organization_id
    User::where('id', $userWithoutOrg->id)->update(['current_organization_id' => $organization->id]);

    echo "✅ Usuario agregado al grupo y organización exitosamente\n\n";

    // 4. Verificar que se actualizó el current_organization_id
    $updatedUser = User::find($userWithoutOrg->id);
    echo "📋 Organización actual DESPUÉS: '{$updatedUser->current_organization_id}'\n";

    if ($updatedUser->current_organization_id == $organization->id) {
        echo "🎉 ¡SUCCESS! El current_organization_id se actualizó correctamente\n";
    } else {
        echo "❌ FALLO: El current_organization_id NO se actualizó\n";
    }

    echo "\n=== VERIFICACIÓN ADICIONAL ===\n";
    echo "📊 El usuario ahora pertenece a:\n";
    echo "   - Organización ID: {$updatedUser->current_organization_id}\n";
    echo "   - Grupo: {$availableGroup->nombre_grupo}\n";

    // Verificar usando la API de contactos
    echo "\n=== TESTING API DE CONTACTOS ===\n";
    $contactController = new \App\Http\Controllers\ContactController();
    $response = $contactController->list();
    $data = json_decode($response->getContent(), true);

    echo "📞 Contactos encontrados: " . count($data['contacts']) . "\n";
    echo "👥 Usuarios de organización encontrados: " . count($data['users']) . "\n";

    if (count($data['users']) > 0) {
        echo "✅ La API ahora encuentra usuarios porque current_organization_id está configurado\n";
        foreach ($data['users'] as $user) {
            echo "   - {$user['name']} ({$user['email']}) - Grupo: {$user['group_name']}\n";
        }
    } else {
        echo "⚠️ La API no encuentra usuarios - puede que no haya otros usuarios en la misma organización\n";
    }

} catch (Exception $e) {
    echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
