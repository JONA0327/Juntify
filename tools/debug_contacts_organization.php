<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Contact;

echo "=== DEBUGGING CONTACTOS Y ORGANIZACIÓN ===\n\n";

// 1. Usuario actual (simulamos como si fuera el primero)
$currentUser = User::first();
if (!$currentUser) {
    echo "ERROR: No hay usuarios en el sistema\n";
    exit;
}

echo "1. Usuario actual:\n";
echo "   - ID: {$currentUser->id}\n";
echo "   - Nombre: {$currentUser->full_name}\n";
echo "   - Email: {$currentUser->email}\n";
echo "   - Organización actual: {$currentUser->current_organization_id}\n\n";

// 2. Todos los usuarios de la misma organización
echo "2. Usuarios en la misma organización ({$currentUser->current_organization_id}):\n";
$sameOrgUsers = User::where('current_organization_id', $currentUser->current_organization_id)
    ->where('id', '!=', $currentUser->id)
    ->get(['id', 'full_name', 'email', 'username', 'current_organization_id']);

if ($sameOrgUsers->count() > 0) {
    foreach ($sameOrgUsers as $user) {
        echo "   - {$user->full_name} ({$user->username}) - {$user->email} [ID: {$user->id}]\n";
    }
} else {
    echo "   - No hay otros usuarios en la misma organización\n";
}

// 3. Todos los usuarios en el sistema por organización
echo "\n3. Todos los usuarios por organización:\n";
$allUsers = User::orderBy('current_organization_id')->get(['id', 'full_name', 'email', 'username', 'current_organization_id']);
$usersByOrg = $allUsers->groupBy('current_organization_id');

foreach ($usersByOrg as $orgId => $users) {
    echo "   Organización {$orgId}:\n";
    foreach ($users as $user) {
        $isCurrent = ($user->id === $currentUser->id) ? " [TÚ]" : "";
        echo "     - {$user->full_name} ({$user->username}) - {$user->email}{$isCurrent}\n";
    }
}

// 4. Contactos existentes del usuario actual
echo "\n4. Contactos del usuario actual:\n";
$contacts = Contact::where('user_id', $currentUser->id)
    ->with('contact')
    ->get();

if ($contacts->count() > 0) {
    foreach ($contacts as $contact) {
        echo "   - {$contact->contact->full_name} ({$contact->contact->email})\n";
    }
} else {
    echo "   - No tienes contactos registrados\n";
}

// 5. Verificar si current_organization_id es null o vacío
echo "\n5. Usuarios con organización null o vacía:\n";
$nullOrgUsers = User::whereNull('current_organization_id')
    ->orWhere('current_organization_id', '')
    ->orWhere('current_organization_id', 0)
    ->get(['id', 'full_name', 'email', 'current_organization_id']);

if ($nullOrgUsers->count() > 0) {
    foreach ($nullOrgUsers as $user) {
        echo "   - {$user->full_name} - Org: '{$user->current_organization_id}'\n";
    }
} else {
    echo "   - Todos los usuarios tienen organización asignada\n";
}

echo "\n=== FIN DEBUG ===\n";
