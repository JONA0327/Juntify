<?php

namespace Database\Factories;

use App\Models\TaskLaravel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskLaravel>
 */
class TaskLaravelFactory extends Factory
{
    protected $model = TaskLaravel::class;

    public function definition(): array
    {
        return [
            'username' => fake()->userName(),
            'meeting_id' => null,
            'tarea' => fake()->sentence(),
            'prioridad' => fake()->randomElement(['alta', 'media', 'baja']),
            'fecha_inicio' => now()->startOfDay(),
            'fecha_limite' => now()->addDays(2)->startOfDay(),
            'hora_limite' => '18:00',
            'descripcion' => fake()->paragraph(),
            'asignado' => fake()->name(),
            'progreso' => 0,
        ];
    }
}
