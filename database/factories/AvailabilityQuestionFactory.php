<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AvailabilityQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'text' => rtrim(fake()->unique()->sentence(), '.').'?',
            'position' => fake()->unique()->numberBetween(1, 100000),
        ];
    }
}
