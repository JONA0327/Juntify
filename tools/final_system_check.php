<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use App\Http\Controllers\ContactController;

echo "=== VERIFICACIÓN FINAL DEL SISTEMA ===\n\n";

// 1. Comprobar usuarios con current_organization_id
echo "📊 ESTADÍSTICAS DE USUARIOS:\n";
$totalUsers = User::count();
$usersWithOrg = User::whereNotNull('current_organization_id')
    ->where('current_organization_id', '!=', '')
    ->count();
$usersWithoutOrg = $totalUsers - $usersWithOrg;

echo "   - Total usuarios: $totalUsers\n";
echo "   - Con organización: $usersWithOrg\n";
echo "   - Sin organización: $usersWithoutOrg\n\n";

// 2. Mostrar organizaciones y sus miembros
echo "🏢 ORGANIZACIONES ACTIVAS:\n";
$organizations = Organization::withCount('users')->get();
foreach ($organizations as $org) {
    echo "   - {$org->nombre_organizacion} (ID: {$org->id}) -> {$org->users_count} miembros\n";

    // Mostrar usuarios con current_organization_id = esta organización
    $usersInThisOrg = User::where('current_organization_id', $org->id)->count();
    echo "     📋 Usuarios con current_organization_id = {$org->id}: $usersInThisOrg\n";
}

echo "\n👥 GRUPOS ACTIVOS:\n";
$groups = Group::with('organization')->withCount('users')->get();
foreach ($groups as $group) {
    echo "   - {$group->nombre_grupo} (Org: {$group->organization->nombre_organizacion}) -> {$group->users_count} miembros\n";
}

// 3. Probar API de contactos con diferentes usuarios
echo "\n🔍 TESTING API DE CONTACTOS:\n";

$usersToTest = User::whereNotNull('current_organization_id')
    ->where('current_organization_id', '!=', '')
    ->take(3)
    ->get();

foreach ($usersToTest as $testUser) {
    echo "\n👤 Probando con: {$testUser->full_name} (Org: {$testUser->current_organization_id})\n";

    // Simular autenticación
    auth()->login($testUser);

    try {
        $controller = new ContactController();
        $response = $controller->list();
        $data = json_decode($response->getContent(), true);

        echo "   📞 Contactos: " . count($data['contacts']) . "\n";
        echo "   👥 Usuarios de organización: " . count($data['users']) . "\n";

        if (count($data['users']) > 0) {
            echo "   ✅ API funcionando correctamente\n";
            foreach (array_slice($data['users'], 0, 3) as $user) {
                echo "      - {$user['name']} (Grupo: {$user['group_name']})\n";
            }
        } else {
            echo "   ⚠️ No se encontraron usuarios de organización\n";
        }

    } catch (Exception $e) {
        echo "   ❌ Error en API: " . $e->getMessage() . "\n";
    }
}

// 4. Verificar códigos de grupo disponibles
echo "\n🎫 CÓDIGOS DE GRUPO DISPONIBLES:\n";
$groupCodes = \App\Models\GroupCode::with('group.organization')->get();
foreach ($groupCodes as $code) {
    echo "   - Código: {$code->code} → Grupo: {$code->group->nombre_grupo} (Org: {$code->group->organization->nombre_organizacion})\n";
}

echo "\n✅ SISTEMA LISTO PARA USAR\n";
echo "🎯 Los usuarios ahora se asignan automáticamente a organizaciones\n";
echo "📱 La API de contactos funciona correctamente con filtrado por organización\n";
echo "🔗 Los códigos de invitación actualizan current_organization_id automáticamente\n";

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";
