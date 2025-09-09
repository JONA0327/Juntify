<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Contact;
use App\Models\Notification;

echo "=== TESTING CONTACTOS FUNCTIONALITY ===\n\n";

// 1. Verificar usuarios existentes
echo "1. Usuarios disponibles:\n";
$users = User::take(5)->get(['id', 'username', 'full_name', 'email', 'current_organization_id']);
foreach ($users as $user) {
    echo "   - {$user->full_name} ({$user->username}) - {$user->email} [Org: {$user->current_organization_id}]\n";
}

// 2. Verificar si existen contactos
echo "\n2. Contactos existentes:\n";
$contacts = Contact::with('contact')->take(5)->get();
if ($contacts->count() > 0) {
    foreach ($contacts as $contact) {
        echo "   - Usuario {$contact->user_id} -> Contacto {$contact->contact->full_name} ({$contact->contact->email})\n";
    }
} else {
    echo "   - No hay contactos registrados\n";
}

// 3. Verificar notificaciones
echo "\n3. Notificaciones de solicitudes de contacto:\n";
$notifications = Notification::where('type', 'contact_request')->take(5)->get();
if ($notifications->count() > 0) {
    foreach ($notifications as $notif) {
        echo "   - De {$notif->remitente} para {$notif->emisor} - Estado: {$notif->status}\n";
    }
} else {
    echo "   - No hay solicitudes de contacto\n";
}

// 4. Test de la API de búsqueda (simulado)
echo "\n4. Test de búsqueda de usuarios:\n";
$testQueries = ['gustavo', 'fernanda', 'leonardo'];
foreach ($testQueries as $query) {
    $results = User::where(function ($q) use ($query) {
            $q->where('email', 'LIKE', "%{$query}%")
              ->orWhere('full_name', 'LIKE', "%{$query}%")
              ->orWhere('username', 'LIKE', "%{$query}%");
        })
        ->limit(3)
        ->get(['full_name', 'email', 'username']);

    echo "   Búsqueda '{$query}': " . $results->count() . " resultados\n";
    foreach ($results as $result) {
        echo "     - {$result->full_name} ({$result->username}) - {$result->email}\n";
    }
}

echo "\n=== FIN DEL TEST ===\n";
