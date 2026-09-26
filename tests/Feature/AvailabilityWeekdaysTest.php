<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityWeekdaysTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->employee = Employee::factory()->create();
        $this->shift = Shift::factory()->create(['visible_by_default' => true]);
        $workcenter = Workcenter::factory()->create();
        $workcenter->shifts()->attach($this->shift);
        foreach ([1, 6] as $weekday) {
            WorkcenterShiftCapacity::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $this->shift->id, 'weekday' => $weekday, 'spots' => 1,
            ]);
        }
    }

    public function test_a_weekend_default_is_stored_for_a_shift_that_runs_that_day(): void
    {
        $this->put("/employees/{$this->employee->id}/availability/6/{$this->shift->id}", ['level' => 'available'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->employee->recurringAvailabilities()->where('weekday', 6)->count());
    }

    public function test_a_default_for_a_day_the_shift_does_not_run_is_rejected(): void
    {
        $this->put("/employees/{$this->employee->id}/availability/2/{$this->shift->id}", ['level' => 'available'])
            ->assertSessionHasErrors('shift');
        $this->put("/employees/{$this->employee->id}/availability/8/{$this->shift->id}", ['level' => 'available'])
            ->assertNotFound();

        $this->assertSame(0, $this->employee->recurringAvailabilities()->count());
    }

    public function test_a_date_override_for_a_day_the_shift_does_not_run_is_rejected(): void
    {
        // 2026-10-06 is a Tuesday, 2026-10-10 a Saturday.
        $this->put("/employees/{$this->employee->id}/availability/dates/2026-10-06", [
            'blocked' => false, 'shifts' => [$this->shift->id => 'available'],
        ])->assertSessionHasErrors('shifts');
        $this->put("/employees/{$this->employee->id}/availability/dates/2026-10-10", [
            'blocked' => false, 'shifts' => [$this->shift->id => 'available'],
        ])->assertSessionHasNoErrors();
        $this->put("/employees/{$this->employee->id}/availability/dates/2026-10-06", ['blocked' => true, 'shifts' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->employee->availabilityOverrides()->count());
    }

    public function test_both_pages_list_the_weekdays_of_each_shift(): void
    {
        $link = $this->employee->personalLink()->create(['token' => 'tok']);

        $this->get("/employees/{$this->employee->id}/edit")
            ->assertInertia(fn ($page) => $page->where('shifts.0.weekdays', [1, 6]));
        $this->get("/personal/{$link->token}")
            ->assertInertia(fn ($page) => $page->where('shifts.0.weekdays', [1, 6]));
    }
}
