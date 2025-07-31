<?php

namespace Database\Factories;

use App\Models\Analyzer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AnalyzerFactory extends Factory
{
    protected $model = Analyzer::class;

    public function definition(): array
    {
        return [
            'id' => Str::random(10),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'icon' => '🔍',
            'system_prompt' => 'system prompt',
            'user_prompt_template' => 'prompt template',
            'temperature' => 0.5,
            'is_system' => false,
            'created_by' => 'tester',
            'updated_by' => 'tester',
        ];
    }
}
