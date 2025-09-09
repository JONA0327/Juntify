<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Http\Controllers\ContactController;

echo "=== TESTING API CON USUARIO SIN ORGANIZACIÓN PERO EN GRUPO ===\n\n";

// Obtener un usuario que NO tenga organización pero SÍ esté en un grupo
$user = User::where('email', 'goku03278@gmail.com')->first();

if (!$user) {
    echo "❌ Usuario sin organización pero en grupo no encontrado\n";
    exit;
}

echo "Usuario de prueba: {$user->full_name} ({$user->email})\n";
echo "Organization ID: '{$user->current_organization_id}'\n\n";

// Crear instancia del controlador
$controller = new ContactController();

// Simular autenticación
auth()->login($user);

echo "=== EJECUTANDO API CON USUARIO SIN ORG PERO EN GRUPO ===\n";

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
