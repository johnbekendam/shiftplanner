<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\PlanningSettings;
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
        $this->get('/planning')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/planning')->assertForbidden();
    }

    public function test_index_defaults_to_the_current_month_with_active_workcenters_and_all_shifts(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['name' => 'Archived', 'archived_at' => now()]);
        Workcenter::factory()->create(['name' => 'Line 1']);
        Shift::factory()->create(['name' => 'Early']);
        $now = Carbon::now();

        $this->get('/planning')->assertOk()
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

        $this->get('/planning?year=2026&month=3')->assertOk()
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

        $response = $this->get('/planning?year=2026&month=9')->assertOk();
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

        $this->get('/planning?year=2026&month=9')->assertOk()
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

        $response = $this->get('/planning?year=2026&month=9')->assertOk();
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

        $this->get('/planning?year=2026&month=9')->assertOk()
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

        $this->get('/planning?year=2026&month=9')->assertOk()
            ->assertInertia(fn ($page) => $page->where('coverage.0.assigned', 0));
    }

    public function test_the_default_date_is_today_and_week_start_is_that_weeks_monday(): void
    {
        $this->actingAsAdmin();
        $now = Carbon::now();
        $mondayOfThisWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('date', $now->toDateString())
                ->where('weekStart', $mondayOfThisWeek)
            );
    }

    public function test_a_non_current_month_defaults_the_date_to_its_first_day(): void
    {
        $this->actingAsAdmin();

        // 2026-03-01 is a Sunday; the Monday of its week is 2026-02-23.
        $this->get('/planning?year=2026&month=3')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('date', '2026-03-01')
                ->where('weekStart', '2026-02-23')
            );
    }

    public function test_an_explicit_date_selects_its_containing_week(): void
    {
        $this->actingAsAdmin();

        // 2026-09-17 is a Thursday; the Monday of its week is 2026-09-14.
        $this->get('/planning?year=2026&month=9&date=2026-09-17')->assertOk()
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
        $response = $this->get('/planning?year=2026&month=9&date=2026-09-01')->assertOk();
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

        $response = $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk();
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

        $response = $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk();
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

        $response = $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk();
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

        $response = $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk();
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
        $response = $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk();
        $cells = collect($response->viewData('page')['props']['weekCells']);

        $this->assertCount(7, $cells);
        $this->assertEqualsCanonicalizing(
            ['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13'],
            $cells->pluck('date')->all(),
        );
    }

    public function test_published_workcenter_weeks_includes_a_published_pair_within_the_month(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id]);

        $response = $this->get('/planning?year=2026&month=9')->assertOk();
        $published = $response->viewData('page')['props']['publishedWorkcenterWeeks'];

        $this->assertEqualsCanonicalizing(
            [['workcenter_id' => $workcenter->id, 'week_start' => '2026-09-07', 'planner_open' => false]],
            $published,
        );
    }

    public function test_published_workcenter_weeks_carries_the_planner_open_flag(): void
    {
        $this->actingAsAdmin();
        $open = Workcenter::factory()->create();
        $frozen = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $open->id, 'planner_open' => true]);
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $frozen->id]);

        $published = collect($this->get('/planning?year=2026&month=9')->viewData('page')['props']['publishedWorkcenterWeeks']);

        $this->assertTrue($published->firstWhere('workcenter_id', $open->id)['planner_open']);
        $this->assertFalse($published->firstWhere('workcenter_id', $frozen->id)['planner_open']);
    }

    public function test_published_workcenter_weeks_keeps_workcenters_independent(): void
    {
        $this->actingAsAdmin();
        $published = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $published->id]);

        $response = $this->get('/planning?year=2026&month=9')->assertOk();
        $publishedPairs = collect($response->viewData('page')['props']['publishedWorkcenterWeeks']);

        $this->assertTrue($publishedPairs->contains('workcenter_id', $published->id));
        $this->assertFalse($publishedPairs->contains('workcenter_id', $other->id));
    }

    public function test_published_workcenter_weeks_includes_a_week_starting_in_the_adjacent_month(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        // 2026-09-01 is a Tuesday; its week starts 2026-08-31 (August).
        PublishedWeek::query()->create(['week_start' => '2026-08-31', 'workcenter_id' => $workcenter->id]);

        $response = $this->get('/planning?year=2026&month=9')->assertOk();
        $published = $response->viewData('page')['props']['publishedWorkcenterWeeks'];

        $this->assertEqualsCanonicalizing(
            [['workcenter_id' => $workcenter->id, 'week_start' => '2026-08-31', 'planner_open' => false]],
            $published,
        );
    }

    public function test_published_workcenter_weeks_is_empty_when_nothing_is_published(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create();

        $this->get('/planning?year=2026&month=9')->assertOk()
            ->assertInertia(fn ($page) => $page->where('publishedWorkcenterWeeks', []));
    }

    public function test_cycle_start_is_null_when_no_period_start_is_configured(): void
    {
        $this->actingAsAdmin();

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('cycleStart', null));
    }

    public function test_cycle_start_resolves_the_two_week_cycle_containing_the_viewed_week(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);

        // 2026-09-10 falls in the second week of the 09-07..09-20 cycle.
        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('cycleStart', '2026-09-07'));
    }

    public function test_generation_run_is_null_when_no_cycle_is_resolved(): void
    {
        $this->actingAsAdmin();

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('generationRun', null));
    }

    public function test_generation_run_is_null_when_the_cycle_has_never_run(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('generationRun', null));
    }

    public function test_generation_run_reflects_the_most_recent_run_for_the_cycle(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_FAILED, 'error' => 'boom']);
        $latest = PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('generationRun.status', PlanGenerationRun::STATUS_RUNNING)
                ->where('generationRun.error', null)
            );

        $this->assertNotNull($latest);
    }

    public function test_generation_run_carries_its_id_and_empty_changes_and_unfulfilled_when_not_done(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);
        $run = PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('generationRun.id', $run->id)
                ->where('generationRun.changes', [])
                ->where('generationRun.unfulfilled', [])
            );
    }

    public function test_generation_run_resolves_changes_and_unfulfilled_to_display_names(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);
        $employee = Employee::factory()->create(['first_name' => 'Anna', 'last_name' => 'Jansen']);
        $workcenter = Workcenter::factory()->create(['name' => 'Line 1']);
        $shift = Shift::factory()->create(['name' => 'Early']);
        PlanGenerationRun::create([
            'cycle_start' => '2026-09-07',
            'status' => PlanGenerationRun::STATUS_DONE,
            'changes' => [
                ['type' => 'added', 'employee_id' => $employee->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08'],
            ],
            'unfulfilled' => [
                ['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'reason' => 'no_eligible_employee'],
            ],
        ]);

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('generationRun.changes.0.type', 'added')
                ->where('generationRun.changes.0.employee_name', 'Anna Jansen')
                ->where('generationRun.changes.0.workcenter_name', 'Line 1')
                ->where('generationRun.changes.0.shift_name', 'Early')
                ->where('generationRun.changes.0.date', '2026-09-08')
                ->where('generationRun.unfulfilled.0.workcenter_name', 'Line 1')
                ->where('generationRun.unfulfilled.0.shift_name', 'Early')
                ->where('generationRun.unfulfilled.0.reason', 'no_eligible_employee')
            );
    }

    public function test_generation_run_falls_back_to_the_id_when_a_referenced_entity_no_longer_exists(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        PlanGenerationRun::create([
            'cycle_start' => '2026-09-07',
            'status' => PlanGenerationRun::STATUS_DONE,
            'changes' => [
                ['type' => 'added', 'employee_id' => 999999, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08'],
            ],
            'unfulfilled' => [],
        ]);

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('generationRun.changes.0.employee_name', '#999999'));
    }

    public function test_generation_run_ignores_a_run_from_a_different_cycle(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-21', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->get('/planning?year=2026&month=9&date=2026-09-10')->assertOk()
            ->assertInertia(fn ($page) => $page->where('generationRun', null));
    }

    public function test_planning_period_is_null_until_both_dates_are_configured(): void
    {
        $this->actingAsAdmin();

        $this->get('/planning')->assertOk()->assertInertia(fn ($page) => $page->where('planningPeriod', null));

        PlanningSettings::current()->update(['period_start' => '2026-09-07']);
        $this->get('/planning')->assertOk()->assertInertia(fn ($page) => $page->where('planningPeriod', null));
    }

    public function test_planning_period_reflects_settings(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-10-18']);

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('planningPeriod.start', '2026-09-07')
                ->where('planningPeriod.end', '2026-10-18')
            );
    }

    public function test_generation_status_is_null_when_the_period_is_not_configured(): void
    {
        $this->actingAsAdmin();

        $this->get('/planning')->assertOk()->assertInertia(fn ($page) => $page->where('generationStatus', null));
    }

    public function test_generation_status_is_inactive_with_no_failures_when_nothing_has_run(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-10-04']);

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('generationStatus.active', false)
                ->where('generationStatus.failedCount', 0)
                ->where('generationStatus.firstError', null)
            );
    }

    public function test_generation_status_is_active_when_any_cycle_in_the_period_is_pending_or_running(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-10-04']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_DONE]);
        PlanGenerationRun::create(['cycle_start' => '2026-09-21', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page->where('generationStatus.active', true));
    }

    public function test_generation_status_reports_failed_cycles_once_none_are_active(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-10-04']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_FAILED, 'error' => 'first boom']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-21', 'status' => PlanGenerationRun::STATUS_FAILED, 'error' => 'second boom']);

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('generationStatus.active', false)
                ->where('generationStatus.failedCount', 2)
                ->has('generationStatus.firstError')
            );
    }

    public function test_generation_status_uses_only_the_latest_run_per_cycle(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-09-20']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_FAILED, 'error' => 'stale']);
        PlanGenerationRun::create(['cycle_start' => '2026-09-07', 'status' => PlanGenerationRun::STATUS_DONE]);

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('generationStatus.active', false)
                ->where('generationStatus.failedCount', 0)
            );
    }

    public function test_generation_status_ignores_a_run_outside_the_current_period(): void
    {
        $this->actingAsAdmin();
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-09-20']);
        PlanGenerationRun::create(['cycle_start' => '2026-10-05', 'status' => PlanGenerationRun::STATUS_RUNNING]);

        $this->get('/planning')->assertOk()
            ->assertInertia(fn ($page) => $page->where('generationStatus.active', false));
    }
}
