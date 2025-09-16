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
            'username' => User::factory()->create()->username,
            'meeting_name' => $this->faker->sentence(3),
            'audio_drive_id' => 'audio-' . $this->faker->uuid(),
            'audio_download_url' => $this->faker->url(),
            'transcript_drive_id' => 'transcript-' . $this->faker->uuid(),
            'transcript_download_url' => $this->faker->url(),
        ];
    }
}
