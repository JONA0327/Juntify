<?php

require_once 'bootstrap/app.php';

use App\Models\TranscriptionTemp;
use App\Models\TaskLaravel;
use Illuminate\Http\Request;
use App\Http\Controllers\TranscriptionTempController;

// Simular usuario
$user = new \stdClass();
$user->id = 1;

// Test directo del controlador
echo "=== Testing TranscriptionTempController::show ===\n\n";

// Simular autenticación
if (!function_exists('auth')) {
    function auth() {
        global $user;
        return (object)['user' => function() use ($user) { return $user; }];
    }
}

// Mock Auth
class MockAuth {
    public static function user() {
        global $user;
        return $user;
    }
}

// Crear instancia del controlador
$controller = new TranscriptionTempController();

// Usar reflexión para llamar al método show
$reflection = new \ReflectionClass($controller);
$showMethod = $reflection->getMethod('show');

try {
    // Intentar simular el método show
    $meeting = TranscriptionTemp::where('id', 11)->first();

    if ($meeting) {
        echo "Reunión encontrada: {$meeting->meeting_name}\n";

        // Cargar tareas manualmente
        $tasks = TaskLaravel::where('meeting_id', $meeting->id)
            ->where('meeting_type', 'temporary')
            ->get();

        echo "Tareas encontradas: " . $tasks->count() . "\n\n";

        if ($tasks->count() > 0) {
            echo "Primeras 3 tareas:\n";
            foreach ($tasks->take(3) as $task) {
                echo "- {$task->tarea}: {$task->descripcion}\n";
            }
        }

        // Simular el formato del controlador
        $dbTasks = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'tarea' => $task->tarea,
                'descripcion' => $task->descripcion,
                'prioridad' => $task->prioridad,
                'asignado' => $task->asignado,
                'fecha_limite' => $task->fecha_limite,
                'hora_limite' => $task->hora_limite,
                'progreso' => $task->progreso,
            ];
        });

        echo "\nFormato JSON para frontend:\n";
        echo json_encode($dbTasks->take(2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } else {
        echo "No se encontró la reunión con ID 11\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

?>
