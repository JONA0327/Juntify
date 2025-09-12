<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificando Organización CERO UNO CERO ===\n";

$org = App\Models\Organization::where('nombre_organizacion', 'CERO UNO CERO')->first();
if ($org) {
    echo "✅ Organization ID: {$org->id}\n";
    echo "✅ Organization name: {$org->nombre_organizacion}\n";
    echo "✅ Admin ID: {$org->admin_id}\n";

    // Buscar el admin
    $admin = App\Models\User::find($org->admin_id);
    if ($admin) {
        echo "✅ Admin username: {$admin->username}\n";
        echo "✅ Admin full name: {$admin->full_name}\n";
    } else {
        echo "❌ Admin user not found\n";
    }

    $folder = $org->folder;
    if ($folder) {
        echo "✅ Root folder: {$folder->name} (Google ID: {$folder->google_id})\n";
        $subfolders = $folder->subfolders;
        echo "📁 Subfolders count: {$subfolders->count()}\n";
        foreach ($subfolders as $sub) {
            echo "  - {$sub->name} (Google ID: {$sub->google_id})\n";
        }
    } else {
        echo "❌ No root folder found\n";
    }
} else {
    echo "❌ Organization not found\n";
}

echo "\n=== Verificando datos de usuario actual ===\n";
$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
if ($user) {
    echo "✅ Username: {$user->username}\n";
    echo "✅ User ID: {$user->id}\n";
    echo "✅ Current org ID: " . ($user->current_organization_id ?: 'NULL') . "\n";

    // Verificar si es admin de alguna organización
    $adminOrgs = App\Models\Organization::where('admin_id', $user->id)->get();
    echo "👑 Admin of organizations: {$adminOrgs->count()}\n";
    foreach ($adminOrgs as $org) {
        echo "  - {$org->nombre_organizacion} (ID: {$org->id})\n";
    }

    // Verificar qué organizaciones tiene el usuario en la tabla pivot
    $orgs = $user->organizations;
    echo "🏢 Member of organizations: {$orgs->count()}\n";
    foreach ($orgs as $org) {
        echo "  - {$org->nombre_organizacion} (ID: {$org->id}, Role: {$org->pivot->rol})\n";
    }

    $orgFolder = $user->organizationFolder;
    if ($orgFolder) {
        echo "✅ User org folder: {$orgFolder->name} (Google ID: {$orgFolder->google_id})\n";
    } else {
        echo "❌ User has no organization folder\n";
    }
}
