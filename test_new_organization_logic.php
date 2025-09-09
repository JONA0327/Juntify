<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Contact;

echo "=== TESTING NUEVA LÓGICA DE ORGANIZACIÓN ===\n\n";

// Simular usuario autenticado
$user = User::first();
echo "Usuario autenticado: {$user->full_name}\n";
echo "Email: {$user->email}\n";
echo "Organización: '{$user->current_organization_id}'\n\n";

// Extraer dominio del email
$userDomain = substr(strrchr($user->email, "@"), 1);
echo "Dominio del email: {$userDomain}\n\n";

// Implementar la nueva lógica
$organizationUsers = collect();

if (!empty($user->current_organization_id)) {
    echo "CASO 1: Usuario tiene organización válida\n";
    $organizationUsers = User::where('current_organization_id', $user->current_organization_id)
        ->where('id', '!=', $user->id)
        ->whereNotNull('current_organization_id')
        ->where('current_organization_id', '!=', '')
        ->limit(20)
        ->get(['id', 'full_name as name', 'email']);
} else {
    echo "CASO 2: Usuario sin organización válida\n";

    if ($userDomain && $userDomain !== 'gmail.com' && $userDomain !== 'hotmail.com' && $userDomain !== 'yahoo.com') {
        echo "SUBCASO 2A: Dominio corporativo detectado ({$userDomain})\n";
        $organizationUsers = User::where('email', 'LIKE', "%@{$userDomain}")
            ->where('id', '!=', $user->id)
            ->limit(20)
            ->get(['id', 'full_name as name', 'email']);
    } else {
        echo "SUBCASO 2B: Dominio genérico ({$userDomain})\n";
        $organizationUsers = User::where('id', '!=', $user->id)
            ->where(function($query) use ($userDomain, $user) {
                $query->whereNotNull('current_organization_id')
                      ->where('current_organization_id', '!=', '');
            })
            ->orWhere(function($query) use ($userDomain, $user) {
                $query->where('email', 'LIKE', "%@{$userDomain}")
                      ->where('id', '!=', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get(['id', 'full_name as name', 'email']);
    }
}

echo "\nUsuarios encontrados: {$organizationUsers->count()}\n\n";

if ($organizationUsers->count() > 0) {
    echo "Usuarios de la organización/dominio:\n";
    foreach ($organizationUsers->take(10) as $u) {
        echo "   - {$u->name} ({$u->email})\n";
    }
    if ($organizationUsers->count() > 10) {
        echo "   ... y " . ($organizationUsers->count() - 10) . " más\n";
    }
} else {
    echo "No se encontraron usuarios relacionados\n";
}

// Test adicional: Ver usuarios por dominios específicos
echo "\n=== ANÁLISIS POR DOMINIOS ===\n";
$domains = User::selectRaw('SUBSTRING_INDEX(email, "@", -1) as domain, COUNT(*) as count')
    ->groupBy('domain')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get();

foreach ($domains as $domain) {
    echo "   - {$domain->domain}: {$domain->count} usuarios\n";
}

echo "\n=== FIN TEST ===\n";
