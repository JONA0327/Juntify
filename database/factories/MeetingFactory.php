<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'date' => $this->faker->dateTime(),
            'duration' => $this->faker->numberBetween(30, 180) . 'm',
            'participants' => $this->faker->numberBetween(1, 10),
            'summary' => $this->faker->paragraph(),
            'recordings_folder_id' => $this->faker->uuid(),
            'username' => User::factory()->create()->username,
            'speaker_map' => ['1' => 'Speaker 1'],
        ];
    }
}
