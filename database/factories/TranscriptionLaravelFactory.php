<?php

namespace Database\Factories;

use App\Models\TranscriptionLaravel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranscriptionLaravel>
 */
class TranscriptionLaravelFactory extends Factory
{
    protected $model = TranscriptionLaravel::class;

    public function definition(): array
    {
        return [
            'username'  => User::factory()->create()->username,
            'transcript' => $this->faker->paragraph,
        ];
    }
}
