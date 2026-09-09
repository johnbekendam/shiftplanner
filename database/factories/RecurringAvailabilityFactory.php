<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecurringAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'weekday' => fake()->numberBetween(1, 7),
            'shift_id' => Shift::factory(),
            'level' => fake()->randomElement(RecurringAvailability::LEVELS),
        ];
    }
}
