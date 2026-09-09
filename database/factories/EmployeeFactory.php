<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Plain names — no titles, prefixes, or suffixes.
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'weekly_hours' => fake()->randomElement(Employee::WEEKLY_HOURS_OPTIONS),
        ];
    }
}
