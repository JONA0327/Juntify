<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Http\Controllers\ContactController;
use Illuminate\Http\Request;

echo "=== TESTING NUEVA API DE CONTACTOS CON GRUPOS ===\n\n";

// Simular usuario autenticado
$user = User::first();
echo "Usuario de prueba: {$user->full_name} ({$user->email})\n";
echo "Organization ID: '{$user->current_organization_id}'\n\n";

// Crear instancia del controlador
$controller = new ContactController();

// Simular autenticación
auth()->login($user);

echo "=== EJECUTANDO NUEVA API ===\n";

try {
    $response = $controller->list();
    $data = json_decode($response->getContent(), true);

    echo "✅ API ejecutada exitosamente\n\n";

    echo "📞 CONTACTOS (" . count($data['contacts']) . ")\n";
    if (empty($data['contacts'])) {
        echo "   - No hay contactos\n";
    } else {
        foreach ($data['contacts'] as $contact) {
            echo "   - {$contact['name']} ({$contact['email']})\n";
        }
    }

    echo "\n👥 USUARIOS DE ORGANIZACIÓN (" . count($data['users']) . ")\n";
    if (empty($data['users'])) {
        echo "   - No hay usuarios de organización\n";
    } else {
        foreach ($data['users'] as $user) {
            echo "   - {$user['name']} ({$user['email']})\n";
            echo "     📂 Grupo: {$user['group_name']}";
            if ($user['group_role']) {
                echo " | 👤 Rol: {$user['group_role']}";
            }
            echo "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
