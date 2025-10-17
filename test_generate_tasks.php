<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TranscriptionTemp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== Probando Generación de Tareas para Reunión Temporal ===\n\n";

// Find user and meeting
$meeting = TranscriptionTemp::find(11);
if (!$meeting) {
    echo "❌ Reunión no encontrada\n";
    exit;
}

$user = User::find($meeting->user_id);
if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit;
}

// Authenticate user
Auth::login($user);

echo "✅ Usuario autenticado: {$user->full_name}\n";
echo "📝 Reunión: {$meeting->title}\n\n";

// Call the controller method directly
$controller = new \App\Http\Controllers\TranscriptionTempController();

try {
    $response = $controller->analyzeAndGenerateTasks($meeting->id);
    $data = $response->getData(true);

    echo "🎯 Resultado del análisis:\n";
    echo "  - Éxito: " . ($data['success'] ? 'Sí' : 'No') . "\n";
    echo "  - Mensaje: " . $data['message'] . "\n";

    if (isset($data['tasks_created'])) {
        echo "  - Tareas creadas: {$data['tasks_created']}\n";
    }

    // Verify tasks in database
    $dbTasks = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)
        ->where('meeting_type', 'temporary')
        ->get();

    echo "\n📊 Verificación en base de datos:\n";
    echo "  - Tareas encontradas: {$dbTasks->count()}\n";

    foreach ($dbTasks as $task) {
        echo "    • {$task->tarea}\n";
        if ($task->descripcion) {
            echo "      Descripción: {$task->descripcion}\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
}

echo "\nPrueba completada.\n";
