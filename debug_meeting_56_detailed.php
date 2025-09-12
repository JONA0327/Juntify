<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Verificando detalles de reunión con ID 56 ===\n";

$meeting = App\Models\Meeting::find(56);
if ($meeting) {
    echo "✅ Reunión encontrada en meetings:\n";
    echo "  - ID: {$meeting->id}\n";
    echo "  - Title: {$meeting->title}\n";
    echo "  - Username: {$meeting->username}\n";
    echo "  - Created at: {$meeting->created_at}\n";
    echo "  - Duration: {$meeting->duration}\n";
} else {
    echo "❌ Reunión con ID 56 no encontrada en meetings\n";
}

// Verificar usuario actual
$user = App\Models\User::where('username', 'Tony_2786_carrillo')->first();
if ($user && $meeting) {
    echo "\n=== Comparación ===\n";
    echo "✅ Usuario actual: {$user->username}\n";
    echo "✅ Owner de reunión: {$meeting->username}\n";
    echo "✅ ¿Mismo usuario?: " . ($user->username === $meeting->username ? 'SÍ' : 'NO') . "\n";

    // Verificar acceso compartido
    $sharedAccess = App\Models\SharedMeeting::where('meeting_id', 56)
        ->where('shared_with', $user->id)
        ->where('status', 'accepted')
        ->exists();
    echo "✅ ¿Acceso compartido?: " . ($sharedAccess ? 'SÍ' : 'NO') . "\n";

    echo "\n=== Verificando el problema exacto ===\n";
    // Intentar la misma consulta que hace el controlador
    $meetingQuery = App\Models\Meeting::where('id', 56);
    if (!$sharedAccess) {
        $meetingQuery->where('username', $user->username);
    }

    try {
        $controllerMeeting = $meetingQuery->firstOrFail();
        echo "✅ La consulta del controlador SÍ encuentra la reunión\n";
    } catch (\Exception $e) {
        echo "❌ La consulta del controlador NO encuentra la reunión: " . $e->getMessage() . "\n";
    }
}
