<?php

namespace Database\Seeders;

use App\Models\BusinessLine;
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
        $businessLineIds = BusinessLine::pluck('id');

        // Give any employee still without a business line a random one.
        if ($businessLineIds->isNotEmpty()) {
            Employee::whereNull('business_line_id')
                ->get()
                ->each(fn (Employee $employee) => $employee->update([
                    'business_line_id' => $businessLineIds->random(),
                ]));
        }

        $missing = self::TARGET - Employee::count();

        if ($missing <= 0) {
            return;
        }

        Employee::factory()
            ->count($missing)
            ->state(fn () => [
                // Spread new employees randomly over the business lines.
                'business_line_id' => $businessLineIds->isEmpty() ? null : $businessLineIds->random(),
            ])
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
