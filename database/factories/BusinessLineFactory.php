<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BusinessLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'abbreviation' => Str::upper(fake()->unique()->lexify('???')),
            'description' => fake()->unique()->words(2, true),
            'target_fte' => fake()->randomFloat(1, 0, 20),
            'position' => fake()->unique()->numberBetween(1, 100000),
        ];
    }
}
