<?php

require_once 'vendor/autoload.php';

// Cargar Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationFolder;

$user = User::where('username', 'Jona0327')->first();

echo "=== ESTADO DEL USUARIO ===\n";
echo "Username: " . $user->username . "\n";
echo "Current Org ID: " . ($user->current_organization_id ?? 'null') . "\n";

if ($user->currentOrganization) {
    echo "Organization Name: " . $user->currentOrganization->name . "\n";
} else {
    echo "Organization: none\n";
}

echo "\n=== RELACIÓN CON ORGANIZACIÓN ===\n";
$orgRelation = $user->organizations()
    ->where('organization_id', $user->current_organization_id)
    ->first();

if ($orgRelation) {
    echo "Role in Organization: " . $orgRelation->pivot->rol . "\n";
} else {
    echo "No membership in current organization\n";
}

echo "\n=== CARPETA ORGANIZACIONAL ===\n";
$orgFolder = $user->organizationFolder;
if ($orgFolder) {
    echo "Organization Folder exists: " . $orgFolder->name . "\n";
    echo "Google ID: " . $orgFolder->google_id . "\n";
} else {
    echo "Organization Folder: NOT FOUND\n";
}

echo "\n=== TODAS LAS ORGANIZACIONES DEL USUARIO ===\n";
foreach ($user->organizations as $org) {
    echo "Org ID: " . $org->id . " - Name: " . $org->name . " - Role: " . $org->pivot->rol . "\n";
}

echo "\n=== TODAS LAS CARPETAS ORGANIZACIONALES ===\n";
$allOrgFolders = OrganizationFolder::all();
foreach ($allOrgFolders as $folder) {
    echo "Org ID: " . $folder->organization_id . " - Name: " . $folder->name . " - Google ID: " . $folder->google_id . "\n";
}
