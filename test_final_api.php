<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== TESTING API FINAL MEJORADA ===\n\n";

// Simular usuario autenticado
$user = User::first();
Auth::login($user);

echo "Usuario autenticado: {$user->full_name}\n";
echo "Email: {$user->email}\n";
echo "Organización: '{$user->current_organization_id}'\n\n";

// Simular exactamente la lógica del controller mejorado
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
    $userDomain = substr(strrchr($user->email, "@"), 1);

    if ($userDomain && !in_array($userDomain, ['gmail.com', 'hotmail.com', 'yahoo.com', 'yahoo.com.mx', 'outlook.com'])) {
        echo "SUBCASO 2A: Dominio corporativo ({$userDomain})\n";
        $organizationUsers = User::where('email', 'LIKE', "%@{$userDomain}")
            ->where('id', '!=', $user->id)
            ->limit(20)
            ->get(['id', 'full_name as name', 'email']);
    } else {
        echo "SUBCASO 2B: Dominio genérico ({$userDomain}) - Priorizando juntify.com\n";
        $organizationUsers = User::where('id', '!=', $user->id)
            ->where(function($query) {
                $query->where('email', 'LIKE', '%@juntify.com')
                      ->orWhere(function($subQuery) {
                          $subQuery->whereNotNull('current_organization_id')
                                   ->where('current_organization_id', '!=', '');
                      });
            })
            ->orderByRaw("CASE WHEN email LIKE '%@juntify.com' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get(['id', 'full_name as name', 'email']);
    }
}

echo "\nUsuarios encontrados: {$organizationUsers->count()}\n\n";

if ($organizationUsers->count() > 0) {
    echo "Usuarios que se mostrarán en 'Usuarios de mi organización':\n";
    foreach ($organizationUsers as $u) {
        $domain = substr(strrchr($u->email, "@"), 1);
        $priority = strpos($u->email, '@juntify.com') !== false ? '[JUNTIFY]' : '';
        echo "   - {$u->name} ({$u->email}) {$priority}\n";
    }
} else {
    echo "No se encontraron usuarios relacionados\n";
}

echo "\n=== VERIFICACIÓN DE CONTACTOS EXISTENTES ===\n";

// Verificar si tengo contactos ya agregados
$contactsIds = \App\Models\Contact::where('user_id', $user->id)->pluck('contact_id')->toArray();
if (!empty($contactsIds)) {
    echo "Contactos ya agregados (se deben ocultar de la lista):\n";
    $existingContacts = User::whereIn('id', $contactsIds)->get(['full_name', 'email']);
    foreach ($existingContacts as $contact) {
        echo "   - {$contact->full_name} ({$contact->email})\n";
    }
} else {
    echo "No tienes contactos agregados aún\n";
}

echo "\n=== FIN TEST ===\n";
