<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Contact;

echo "=== SIMULANDO API /api/contacts ===\n\n";

// Simular usuario autenticado (el primero en la BD)
$user = User::first();

echo "Usuario autenticado: {$user->full_name} (Org: '{$user->current_organization_id}')\n\n";

// Simular la lógica del ContactController->list()
$contacts = Contact::with('contact')
    ->where('user_id', $user->id)
    ->get()
    ->map(function ($c) {
        return [
            'id' => $c->id,
            'name' => $c->contact->full_name,
            'email' => $c->contact->email,
        ];
    });

echo "Contactos del usuario:\n";
if ($contacts->count() > 0) {
    foreach ($contacts as $contact) {
        echo "   - {$contact['name']} ({$contact['email']})\n";
    }
} else {
    echo "   - No tiene contactos\n";
}

// Simular usuarios de organización
echo "\nUsuarios de la misma organización:\n";
$users = User::where('current_organization_id', $user->current_organization_id)
    ->where('id', '!=', $user->id)
    ->get(['id', 'full_name as name', 'email']);

echo "Query ejecutada: current_organization_id = '{$user->current_organization_id}'\n";
echo "Resultados encontrados: {$users->count()}\n\n";

if ($users->count() > 0) {
    $limitedUsers = $users->take(10); // Solo mostrar los primeros 10
    foreach ($limitedUsers as $u) {
        echo "   - {$u->name} ({$u->email})\n";
    }
    if ($users->count() > 10) {
        echo "   ... y " . ($users->count() - 10) . " usuarios más\n";
    }
} else {
    echo "   - No hay usuarios en la organización\n";
}

echo "\n=== SOLUCIÓN PROPUESTA ===\n";
echo "Problema detectado: El usuario actual y muchos otros tienen current_organization_id VACÍO\n";
echo "Esto causa que se incluyan todos los usuarios con organización vacía como de la 'misma organización'\n\n";

echo "Opciones para solucionarlo:\n";
echo "1. Asignar una organización por defecto a los usuarios sin organización\n";
echo "2. Filtrar solo usuarios con organización válida (no vacía)\n";
echo "3. Crear grupos más específicos por dominio de email\n";

echo "\n=== FIN SIMULACIÓN ===\n";
