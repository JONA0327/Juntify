<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "=== BUSCANDO TAREAS ASIGNADAS A jona03278@gmail.com ===\n\n";

// Buscar el usuario
$jona = \App\Models\User::where('email', 'jona03278@gmail.com')->first();

if (!$jona) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "Usuario encontrado: {$jona->username} (ID: {$jona->id})\n\n";

// Buscar tareas asignadas a este usuario
$assignedTasks = \App\Models\TaskLaravel::with(['meeting:id,username,meeting_name'])
    ->where('assigned_user_id', $jona->id)
    ->get();

if ($assignedTasks->isEmpty()) {
    echo "❌ No hay tareas asignadas a este usuario\n";
    exit(0);
}

echo "=== TAREAS ASIGNADAS ({$assignedTasks->count()}) ===\n";

foreach ($assignedTasks as $task) {
    echo "ID: {$task->id}\n";
    echo "Tarea: " . substr($task->tarea, 0, 60) . "...\n";
    echo "Dueño: {$task->username}\n";
    echo "Estado: {$task->assignment_status}\n";
    echo "Reunión: " . ($task->meeting ? $task->meeting->meeting_name : 'N/A') . "\n";
    echo "Dueño reunión: " . ($task->meeting ? $task->meeting->username : 'N/A') . "\n";
    echo "---\n";
}

echo "\n¿Desasignar TODAS las tareas? (y/N): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) === 'y' || strtolower($confirm) === 'yes') {
    foreach ($assignedTasks as $task) {
        $task->update([
            'assigned_user_id' => null,
            'assignment_status' => 'not_assigned',
            'fecha_limite' => null,
            'hora_limite' => null
        ]);
        echo "✅ Desasignada tarea ID {$task->id}: " . substr($task->tarea, 0, 40) . "...\n";
    }
    echo "\n🎉 Todas las tareas han sido desasignadas\n";
} else {
    echo "❌ Operación cancelada\n";
}

echo "\nScript completado.\n";
