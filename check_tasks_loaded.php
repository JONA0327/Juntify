<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TranscriptionTemp;
use App\Models\TaskLaravel;

$meeting = TranscriptionTemp::find(11);
if (!$meeting) {
    echo "Reunión no encontrada\n";
    exit;
}

echo "Reunión encontrada: " . $meeting->title . "\n";

// Check raw tasks in database
$rawTasks = TaskLaravel::where('meeting_id', 11)->get();

echo "Tareas con meeting_id 11: " . $rawTasks->count() . "\n";
foreach ($rawTasks as $task) {
    echo "- ID: {$task->id}, Meeting ID: {$task->meeting_id}, Type: {$task->meeting_type}, Task: " . substr($task->tarea, 0, 50) . "\n";
}

// Test the relationship query manually
echo "\nTesting relationship query manually:\n";
$manualQuery = TaskLaravel::where('meeting_id', 11)
    ->where('meeting_type', 'temporary')
    ->get();

echo "Manual query result: " . $manualQuery->count() . "\n";

// Try to debug the relationship
echo "\nDebugging relationship:\n";
$meeting = TranscriptionTemp::find(11);
echo "Meeting ID: " . $meeting->id . "\n";

// Let's see what the relationship query generates
$relationQuery = $meeting->tasks();
echo "Relationship query SQL: " . $relationQuery->toSql() . "\n";
echo "Relationship query bindings: " . json_encode($relationQuery->getBindings()) . "\n";

$relationResults = $relationQuery->get();
echo "Relationship results: " . $relationResults->count() . "\n";
