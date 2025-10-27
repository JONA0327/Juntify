<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

echo "=== DIAGNÓSTICO DE PERMISOS ===\n\n";

// Buscar usuario por email
$user = \App\Models\User::where('email', 'jona03278@gmail.com')->first();

if (!$user) {
    echo "❌ No se encontró usuario con email 'jona03278@gmail.com'\n";
    echo "Usuarios disponibles:\n";
    $users = \App\Models\User::take(5)->get(['id', 'username', 'email']);
    foreach ($users as $u) {
        echo "  - {$u->username} ({$u->email})\n";
    }
    exit(1);
}

echo "✅ Usuario encontrado:\n";
echo "ID: {$user->id}\n";
echo "Username: {$user->username}\n";
echo "Email: {$user->email}\n";
echo "Organización actual: {$user->current_organization_id}\n\n";

// Obtener las últimas tareas para verificar permisos
echo "=== TAREAS RECIENTES ===\n";
$tasks = \App\Models\TaskLaravel::with(['meeting:id,username,meeting_name'])
    ->orderBy('id', 'desc')
    ->take(5)
    ->get();

foreach ($tasks as $task) {
    echo "ID: {$task->id}\n";
    echo "Título: " . substr($task->tarea, 0, 50) . "...\n";
    echo "Dueño de tarea: {$task->username}\n";
    echo "Dueño de reunión: " . ($task->meeting ? $task->meeting->username : 'N/A') . "\n";
    echo "Asignada a ID: " . ($task->assigned_user_id ?: 'NULL') . "\n";

    // Verificar permisos para este usuario
    $isTaskOwner = $task->username === $user->username;
    $isMeetingOwner = $task->meeting && $task->meeting->username === $user->username;
    $isAssignee = $task->assigned_user_id === $user->id;

    echo "Permisos para jona03278@gmail.com:\n";
    echo "  - Es dueño de tarea: " . ($isTaskOwner ? 'SÍ' : 'NO') . "\n";
    echo "  - Es dueño de reunión: " . ($isMeetingOwner ? 'SÍ' : 'NO') . "\n";
    echo "  - Es asignado: " . ($isAssignee ? 'SÍ' : 'NO') . "\n";
    echo "  - PUEDE EDITAR: " . (($isTaskOwner || $isMeetingOwner) ? 'SÍ' : 'NO') . "\n";
    echo "---\n";
}

echo "\nScript completado.\n";
