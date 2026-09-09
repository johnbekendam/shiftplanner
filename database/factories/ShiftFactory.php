<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    public function definition(): array
    {
        $startHour = fake()->numberBetween(0, 20);

        return [
            'name' => fake()->unique()->words(2, true),
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:00', $startHour + 3),
        ];
    }
}
