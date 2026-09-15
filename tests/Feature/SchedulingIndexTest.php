<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PublishedWeek;
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

    public function test_the_default_date_is_today_and_week_start_is_that_weeks_monday(): void
    {
        $this->actingAsAdmin();
        $now = Carbon::now();
        $mondayOfThisWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->get('/scheduling')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('date', $now->toDateString())
                ->where('weekStart', $mondayOfThisWeek)
            );
    }

    public function test_a_non_current_month_defaults_the_date_to_its_first_day(): void
    {
        $this->actingAsAdmin();

        // 2026-03-01 is a Sunday; the Monday of its week is 2026-02-23.
        $this->get('/scheduling?year=2026&month=3')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('date', '2026-03-01')
                ->where('weekStart', '2026-02-23')
            );
    }

    public function test_an_explicit_date_selects_its_containing_week(): void
    {
        $this->actingAsAdmin();

        // 2026-09-17 is a Thursday; the Monday of its week is 2026-09-14.
        $this->get('/scheduling?year=2026&month=9&date=2026-09-17')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('date', '2026-09-17')
                ->where('weekStart', '2026-09-14')
            );
    }

    public function test_a_week_spanning_two_months_includes_cells_from_the_adjacent_month(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id,
            'weekday' => Carbon::parse('2026-08-31')->isoWeekday(), 'spots' => 2,
        ]);

        // 2026-09-01 is a Tuesday; its week starts 2026-08-31 (August).
        $response = $this->get('/scheduling?year=2026&month=9&date=2026-09-01')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);
        $entry = $cells->firstWhere('date', '2026-08-31');

        $this->assertNotNull($entry);
        $this->assertSame(2, $entry['spots']);
    }

    public function test_week_cells_carry_full_assignment_detail_and_the_overridden_flag(): void
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
        $employee = Employee::factory()->create(['first_name' => 'Anna', 'last_name' => 'Jansen']);
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date->toDateString(),
            'fixed' => true,
        ]);

        $response = $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);
        $entry = $cells->firstWhere('date', '2026-09-10');

        $this->assertNotNull($entry);
        $this->assertSame(5, $entry['spots']);
        $this->assertTrue($entry['overridden']);
        $this->assertCount(1, $entry['assignments']);
        $this->assertSame('Anna Jansen', $entry['assignments'][0]['employee_name']);
        $this->assertTrue($entry['assignments'][0]['fixed']);
    }

    public function test_week_cells_include_a_day_with_zero_spots_when_the_shift_is_attached(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        // No capacity for Thursday (2026-09-10) → 0 spots that day, but the shift is attached.

        $response = $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);
        $entry = $cells->firstWhere('date', '2026-09-10');

        $this->assertNotNull($entry);
        $this->assertSame(0, $entry['spots']);
        $this->assertSame([], $entry['assignments']);
    }

    public function test_week_cells_exclude_a_shift_not_attached_to_the_workcenter(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create();
        Shift::factory()->create(); // not attached to any workcenter

        $response = $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);

        $this->assertCount(0, $cells);
    }

    public function test_week_cells_exclude_archived_workcenters(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['archived_at' => now()]);
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 4, 'spots' => 3,
        ]);

        $response = $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);

        $this->assertCount(0, $cells);
    }

    public function test_week_cells_has_one_entry_per_day_for_each_attached_shift(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);

        // 2026-09-10 is a Thursday; its week runs 2026-09-07 through 2026-09-13.
        $response = $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);

        $this->assertCount(7, $cells);
        $this->assertEqualsCanonicalizing(
            ['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13'],
            $cells->pluck('date')->all(),
        );
    }

    public function test_week_published_is_true_when_the_selected_week_is_published(): void
    {
        $this->actingAsAdmin();
        PublishedWeek::query()->create(['week_start' => '2026-09-07']);

        $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('weekPublished', true));
    }

    public function test_week_published_is_false_when_the_selected_week_is_not_published(): void
    {
        $this->actingAsAdmin();

        $this->get('/scheduling?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('weekPublished', false));
    }

    public function test_published_days_marks_every_day_in_a_published_week(): void
    {
        $this->actingAsAdmin();
        // 2026-09-10 is a Thursday; its week runs 2026-09-07 through 2026-09-13.
        PublishedWeek::query()->create(['week_start' => '2026-09-07']);

        $response = $this->get('/scheduling?year=2026&month=9')->assertOk();
        $publishedDays = $response->viewData('page')['props']['publishedDays'];

        foreach ([7, 8, 9, 10, 11, 12, 13] as $day) {
            $this->assertTrue($publishedDays[$day] ?? false, "day {$day} should be published");
        }
        $this->assertArrayNotHasKey(6, $publishedDays);
        $this->assertArrayNotHasKey(14, $publishedDays);
    }

    public function test_published_days_includes_days_from_a_week_starting_in_the_adjacent_month(): void
    {
        $this->actingAsAdmin();
        // 2026-09-01 is a Tuesday; its week starts 2026-08-31 (August).
        PublishedWeek::query()->create(['week_start' => '2026-08-31']);

        $response = $this->get('/scheduling?year=2026&month=9')->assertOk();
        $publishedDays = $response->viewData('page')['props']['publishedDays'];

        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            $this->assertTrue($publishedDays[$day] ?? false, "day {$day} should be published");
        }
        $this->assertArrayNotHasKey(7, $publishedDays);
    }

    public function test_published_days_is_empty_when_nothing_is_published(): void
    {
        $this->actingAsAdmin();

        $this->get('/scheduling?year=2026&month=9')->assertOk()
            ->assertInertia(fn ($page) => $page->where('publishedDays', []));
    }
}
