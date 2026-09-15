<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_only_missing_existing_cells_as_available(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();
        $employee->recurringAvailabilities()->create([
            'weekday' => 1,
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);

        $migration = require base_path('database/migrations/2026_09_15_000004_backfill_existing_availability_defaults.php');
        $migration->up();

        $this->assertSame(5, RecurringAvailability::where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('recurring_availabilities', [
            'employee_id' => $employee->id,
            'weekday' => 1,
            'shift_id' => $shift->id,
            'level' => 'unavailable',
        ]);
        $this->assertSame(4, RecurringAvailability::where('employee_id', $employee->id)
            ->where('level', 'available')
            ->count());
    }
}