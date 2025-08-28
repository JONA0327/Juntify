<?php

require 'vendor/autoload.php';
require 'bootstrap/app.php';

$app = app();

$task = App\Models\TaskLaravel::latest()->first();

if ($task) {
    echo "ID: " . $task->id . "\n";
    echo "Tarea: " . $task->tarea . "\n";
    echo "Fecha límite (raw): " . $task->getAttributes()['fecha_limite'] . "\n";
    echo "Fecha límite (cast): " . $task->fecha_limite . "\n";
    echo "Hora límite: " . $task->hora_limite . "\n";
    echo "Formato fecha para HTML5: " . ($task->fecha_limite ? $task->fecha_limite->format('Y-m-d') : 'null') . "\n";
} else {
    echo "No hay tareas en la base de datos\n";
}
