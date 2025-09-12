<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Agregando usuario Tony a la organización CERO UNO CERO ===\n";

$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
$org = App\Models\Organization::where('nombre_organizacion', 'CERO UNO CERO')->first();

if ($user && $org) {
    echo "✅ Usuario: {$user->username} (ID: {$user->id})\n";
    echo "✅ Organización: {$org->nombre_organizacion} (ID: {$org->id})\n";

    // Verificar si ya es miembro
    $existingMembership = $user->organizations()->where('organization_id', $org->id)->first();

    if ($existingMembership) {
        echo "⚠️ El usuario ya es miembro con rol: {$existingMembership->pivot->rol}\n";
    } else {
        // Agregar como administrador
        $user->organizations()->attach($org->id, [
            'rol' => 'administrador',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "✅ Usuario agregado como administrador\n";
    }

    // Establecer current_organization_id
    $user->update(['current_organization_id' => $org->id]);
    echo "✅ current_organization_id establecido: {$org->id}\n";

    // Verificar el resultado
    $user->refresh();
    echo "✅ Verificación - Current org ID: {$user->current_organization_id}\n";

    $orgFolder = $user->organizationFolder;
    if ($orgFolder) {
        echo "✅ Organization folder: {$orgFolder->name} (Google ID: {$orgFolder->google_id})\n";
    } else {
        echo "❌ No organization folder found\n";
    }

} else {
    echo "❌ Usuario o organización no encontrados\n";
}
