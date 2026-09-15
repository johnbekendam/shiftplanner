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

    public function test_index_defaults_to_the_current_month_with_active_workcenters_and_all_shifts(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['name' => 'Archived', 'archived_at' => now()]);
        Workcenter::factory()->create(['name' => 'Line 1']);
        Shift::factory()->create(['name' => 'Early']);
        $now = Carbon::now();

        $this->get('/scheduling')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scheduling')
                ->has('workcenters', 1)
                ->where('workcenters.0.name', 'Line 1')
                ->has('shifts', 1)
                ->where('year', $now->year)
                ->where('month', $now->month)
                ->where('coverage', [])
            );
    }

    public function test_an_explicit_year_and_month_is_honored(): void
    {
        $this->actingAsAdmin();

        $this->get('/scheduling?year=2026&month=3')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('year', 2026)
                ->where('month', 3)
            );
    }

    public function test_a_coverage_entry_appears_for_a_day_with_spots_and_carries_the_assigned_count(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        $date = Carbon::create(2026, 9, 10); // a Thursday
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $date->isoWeekday(), 'spots' => 3,
        ]);
        $employee = Employee::factory()->create();
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date->toDateString(),
        ]);

        $response = $this->get('/scheduling?year=2026&month=9')->assertOk();
        $coverage = collect($response->viewData('page')['props']['coverage']);
        $entry = $coverage->firstWhere('date', '2026-09-10');

        $this->assertNotNull($entry);
        $this->assertSame($workcenter->id, $entry['workcenter_id']);
        $this->assertSame($shift->id, $entry['shift_id']);
        $this->assertSame(3, $entry['spots']);
        $this->assertSame(1, $entry['assigned']);
    }

    public function test_a_day_with_zero_required_spots_produces_no_coverage_entry(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        // No capacity row for any weekday in September 2026 → spots is 0 every day.

        $this->get('/scheduling?year=2026&month=9')->assertOk()
            ->assertInertia(fn ($page) => $page->where('coverage', []));
    }

    public function test_a_date_override_wins_over_the_weekday_capacity_in_coverage(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        $date = Carbon::create(2026, 9, 10);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $date->isoWeekday(), 'spots' => 3,
        ]);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date->toDateString(), 'spots' => 5,
        ]);

        $response = $this->get('/scheduling?year=2026&month=9')->assertOk();
        $coverage = collect($response->viewData('page')['props']['coverage']);
        $entry = $coverage->firstWhere('date', '2026-09-10');

        $this->assertNotNull($entry);
        $this->assertSame(5, $entry['spots']);
    }

    public function test_coverage_only_includes_active_workcenters(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['archived_at' => now()]);
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        $date = Carbon::create(2026, 9, 10);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $date->isoWeekday(), 'spots' => 3,
        ]);

        $this->get('/scheduling?year=2026&month=9')->assertOk()
            ->assertInertia(fn ($page) => $page->where('coverage', []));
    }

    public function test_assignments_outside_the_visible_month_do_not_affect_the_assigned_count(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        $date = Carbon::create(2026, 9, 10);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $date->isoWeekday(), 'spots' => 3,
        ]);
        // Same weekday, different month.
        ShiftAssignment::factory()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-08-13',
        ]);

        $this->get('/scheduling?year=2026&month=9')->assertOk()
            ->assertInertia(fn ($page) => $page->where('coverage.0.assigned', 0));
    }
}
