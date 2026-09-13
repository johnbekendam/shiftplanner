<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\Workcenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'workcenter_id' => Workcenter::factory(),
            'shift_id' => Shift::factory(),
            'date' => fake()->date(),
            'fixed' => false,
        ];
    }
}
