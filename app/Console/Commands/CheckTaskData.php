<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TaskLaravel;

class CheckTaskData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:task-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check task data for debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Buscar tareas con fecha límite
        $tasks = TaskLaravel::whereNotNull('fecha_limite')->limit(5)->get();

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                $this->info("=== Tarea ID: " . $task->id . " ===");
                $this->info("Tarea: " . $task->tarea);
                $this->info("Fecha límite (raw): " . $task->getAttributes()['fecha_limite']);
                $this->info("Fecha límite (cast): " . $task->fecha_limite);
                $this->info("Hora límite: " . $task->hora_limite);
                $this->info("Formato fecha para HTML5: " . ($task->fecha_limite ? $task->fecha_limite->format('Y-m-d') : 'null'));
                $this->info("");
            }
        } else {
            $this->info("No hay tareas con fecha límite en la base de datos");

            // Mostrar todas las tareas
            $allTasks = TaskLaravel::limit(5)->get();
            $this->info("Mostrando las últimas 5 tareas:");
            foreach ($allTasks as $task) {
                $this->info("ID: {$task->id} - {$task->tarea} - Fecha: {$task->getAttributes()['fecha_limite']} - Hora: {$task->hora_limite}");
            }
        }
    }
}
