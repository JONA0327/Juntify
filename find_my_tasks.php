<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "=== BUSCANDO TAREAS DE JONA0327 ===\n\n";

$user = \App\Models\User::where('email', 'jona03278@gmail.com')->first();

if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "Usuario: {$user->username} ({$user->email})\n\n";

// Buscar tareas creadas por Jona0327
echo "=== TAREAS CREADAS POR TI ===\n";
$myTasks = \App\Models\TaskLaravel::where('username', $user->username)
    ->with(['meeting:id,username,meeting_name'])
    ->take(10)
    ->get();

if ($myTasks->count() > 0) {
    foreach ($myTasks as $task) {
        echo "✅ ID: {$task->id} - " . substr($task->tarea, 0, 60) . "...\n";
        echo "   Reunión: " . ($task->meeting ? $task->meeting->meeting_name : 'N/A') . "\n";
    }
} else {
    echo "❌ No tienes tareas creadas por ti\n";
}

// Buscar tareas asignadas a Jona0327
echo "\n=== TAREAS ASIGNADAS A TI ===\n";
$assignedTasks = \App\Models\TaskLaravel::where('assigned_user_id', $user->id)
    ->with(['meeting:id,username,meeting_name'])
    ->take(10)
    ->get();

if ($assignedTasks->count() > 0) {
    foreach ($assignedTasks as $task) {
        echo "✅ ID: {$task->id} - " . substr($task->tarea, 0, 60) . "...\n";
        echo "   Dueño: {$task->username}\n";
        echo "   Estado: {$task->assignment_status}\n";
    }
} else {
    echo "❌ No tienes tareas asignadas\n";
}

// Buscar reuniones creadas por Jona0327
echo "\n=== REUNIONES CREADAS POR TI ===\n";
$myMeetings = \App\Models\TranscriptionLaravel::where('username', $user->username)
    ->take(5)
    ->get(['id', 'username', 'meeting_name']);

if ($myMeetings->count() > 0) {
    foreach ($myMeetings as $meeting) {
        echo "🎤 ID: {$meeting->id} - {$meeting->meeting_name}\n";

        // Buscar tareas en esta reunión
        $tasksInMeeting = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)->count();
        echo "   Tareas: {$tasksInMeeting}\n";
    }
} else {
    echo "❌ No tienes reuniones creadas\n";
}

echo "\n=== RECOMENDACIONES ===\n";
echo "Para poder editar tareas, necesitas:\n";
echo "1. 📝 Crear tus propias tareas\n";
echo "2. 🎤 Crear una reunión y agregar tareas a ella\n";
echo "3. 📨 Que alguien te asigne una tarea (para editar solo progreso)\n\n";

echo "Script completado.\n";
