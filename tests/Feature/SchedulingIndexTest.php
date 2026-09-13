<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingIndexTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/scheduling')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/scheduling')->assertForbidden();
    }

    public function test_index_defaults_to_the_first_active_workcenter_and_this_weeks_monday(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['name' => 'Archived', 'position' => 0, 'archived_at' => now()]);
        $first = Workcenter::factory()->create(['name' => 'Line 1', 'position' => 1]);
        Workcenter::factory()->create(['name' => 'Line 2', 'position' => 2]);

        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->get('/scheduling')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scheduling')
                ->has('workcenters', 2)
                ->where('workcenterId', $first->id)
                ->where('weekStart', $monday)
                ->has('days', 7)
                ->where('days.0', $monday)
            );
    }

    public function test_index_returns_an_empty_grid_when_no_active_workcenter_exists(): void
    {
        $this->actingAsAdmin();

        $this->get('/scheduling')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scheduling')
                ->where('workcenterId', null)
                ->where('shifts', [])
                ->where('cells', [])
            );
    }

    public function test_week_start_normalizes_to_that_weeks_monday(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create();

        // 2026-09-17 is a Thursday.
        $this->get('/scheduling?week_start=2026-09-17')->assertOk()
            ->assertInertia(fn ($page) => $page->where('weekStart', '2026-09-14'));
    }

    public function test_grid_carries_shifts_capacity_and_assignments_for_the_selected_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);
        $date = Carbon::now()->startOfWeek(Carbon::MONDAY);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $date->isoWeekday(), 'spots' => 3,
        ]);
        $employee = Employee::factory()->create(['first_name' => 'Anna', 'last_name' => 'Jansen']);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date->toDateString(),
            'fixed' => true,
        ]);

        $this->get("/scheduling?workcenter_id={$workcenter->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shifts', 1)
                ->where('shifts.0.name', 'Early')
                ->has('cells', 7)
                ->where('cells.0.shift_id', $shift->id)
                ->where('cells.0.date', $date->toDateString())
                ->where('cells.0.spots', 3)
                ->where('cells.0.overridden', false)
                ->has('cells.0.assignments', 1)
                ->where('cells.0.assignments.0.employee_name', 'Anna Jansen')
                ->where('cells.0.assignments.0.fixed', true)
            );
    }

    public function test_a_cell_with_a_date_override_is_flagged_overridden(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        $date = Carbon::now()->startOfWeek(Carbon::MONDAY);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date->toDateString(), 'spots' => 1,
        ]);

        $this->get("/scheduling?workcenter_id={$workcenter->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cells.0.overridden', true)
                ->where('cells.0.spots', 1)
            );
    }

    public function test_an_unknown_workcenter_id_falls_back_to_the_first_active_one(): void
    {
        $this->actingAsAdmin();
        $first = Workcenter::factory()->create(['position' => 1]);

        $this->get('/scheduling?workcenter_id=999999')->assertOk()
            ->assertInertia(fn ($page) => $page->where('workcenterId', $first->id));
    }
}
