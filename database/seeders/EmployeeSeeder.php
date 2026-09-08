<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeHoliday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    /** How many employees the list should hold after seeding. */
    private const TARGET = 50;

    public function run(): void
    {
        $missing = self::TARGET - Employee::count();

        if ($missing <= 0) {
            return;
        }

        Employee::factory()
            ->count($missing)
            ->create()
            ->each(function (Employee $employee) {
                // Most employees have a personal link.
                if (fake()->boolean(80)) {
                    $employee->personalLink()->create(['token' => Str::random(40)]);
                }

                // Some employees have one or two holiday ranges.
                if (fake()->boolean(40)) {
                    EmployeeHoliday::factory()
                        ->count(fake()->numberBetween(1, 2))
                        ->for($employee)
                        ->create();
                }
            });
    }
}
