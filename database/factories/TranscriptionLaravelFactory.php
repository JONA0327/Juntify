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
            'meeting_name' => $this->faker->sentence,
            'audio_download_url' => 'https://example.com/audio.mp3',
            'transcript' => $this->faker->paragraph,
        ];
    }
}
