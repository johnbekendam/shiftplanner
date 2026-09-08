<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeHolidayFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+3 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(0, 13).' days');

        return [
            'employee_id' => Employee::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'note' => fake()->optional()->sentence(3),
        ];
    }
}
