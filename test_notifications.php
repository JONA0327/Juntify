<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

echo "=== PRUEBA DE NOTIFICACIONES ===\n\n";

try {
    // 1. Verificar columnas de la tabla
    echo "1. Verificando columnas de la tabla notifications:\n";
    $columns = Schema::getColumnListing('notifications');
    echo "Columnas: " . implode(', ', $columns) . "\n\n";

    // 2. Verificar usuarios existentes
    echo "2. Verificando estructura de usuarios:\n";
    $userColumns = Schema::getColumnListing('users');
    echo "Columnas de users: " . implode(', ', $userColumns) . "\n";

    $users = App\Models\User::select('id')->limit(5)->get();
    echo "Primeros 5 usuarios por ID:\n";
    foreach ($users as $user) {
        echo "ID: {$user->id}\n";
    }
    echo "\n";

    // 3. Contar notificaciones
    echo "3. Total de notificaciones en la tabla:\n";
    $count = App\Models\Notification::count();
    echo "Total: $count\n\n";

    // 4. Crear una notificación de prueba con usuario válido
    echo "4. Creando notificación de prueba:\n";
    $firstUser = $users->first();
    if ($firstUser) {
        $notification = new App\Models\Notification();
        $notification->emisor = $firstUser->id; // usuario destino (UUID)
        $notification->remitente = $firstUser->id; // usuario que envía (UUID)
        $notification->message = "Test de notificación con UUID";
        $notification->type = "test";
        $notification->title = "Prueba UUID";
        $notification->status = "pending";
        $result = $notification->save();
        echo "Notificación creada: " . ($result ? "SÍ" : "NO") . "\n";
        echo "ID: " . $notification->id . "\n";
        echo "Usuario UUID: " . $firstUser->id . "\n\n";

        // 5. Probar el controlador directamente
        echo "5. Probando el controlador NotificationController:\n";

        // Simular usuario autenticado
        Auth::loginUsingId($firstUser->id);

        $controller = new App\Http\Controllers\NotificationController();
        $response = $controller->index();

        echo "Código de respuesta: " . $response->getStatusCode() . "\n";
        echo "Contenido de respuesta: " . $response->getContent() . "\n\n";
    } else {
        echo "No hay usuarios en la tabla users\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
