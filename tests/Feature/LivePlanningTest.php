<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivePlanningTest extends TestCase
{
    use RefreshDatabase;

    private const THIS_WEEK = '2026-09-21';

    private const NEXT_WEEK = '2026-09-28';

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, so the current week starts on Monday 2026-09-21.
        Carbon::setTestNow('2026-09-23 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A workcenter with one shift attached and `$spots` open spots on every weekday. */
    private function workcenterWithShift(int $spots = 2, array $shift = []): array
    {
        $workcenter = Workcenter::factory()->create(['name' => 'Assembly']);
        $shift = Shift::factory()->create($shift + ['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);

        foreach (range(1, 7) as $weekday) {
            WorkcenterShiftCapacity::create([
                'workcenter_id' => $workcenter->id,
                'shift_id' => $shift->id,
                'weekday' => $weekday,
                'spots' => $spots,
            ]);
        }

        return [$workcenter, $shift];
    }

    private function publish(Workcenter $workcenter, string $weekStart): void
    {
        PublishedWeek::create(['week_start' => $weekStart, 'workcenter_id' => $workcenter->id]);
    }

    private function assign(Workcenter $workcenter, Shift $shift, string $date, string $first, string $last = 'Smith'): ShiftAssignment
    {
        return ShiftAssignment::factory()->create([
            'employee_id' => Employee::factory()->create(['first_name' => $first, 'last_name' => $last])->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date,
        ]);
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_the_page_opens_by_token_without_login(): void
    {
        [$workcenter] = $this->workcenterWithShift();

        $this->get("/live/{$workcenter->live_token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Live')
                ->where('workcenter.name', 'Assembly'));
    }

    public function test_an_unknown_token_returns_404(): void
    {
        $this->workcenterWithShift();

        $this->get('/live/not-a-real-token')->assertNotFound();
    }

    public function test_an_archived_workcenter_returns_404(): void
    {
        [$workcenter] = $this->workcenterWithShift();
        $workcenter->update(['archived_at' => now()]);

        $this->get("/live/{$workcenter->live_token}")->assertNotFound();
    }

    public function test_the_response_is_not_indexed_and_not_cached(): void
    {
        [$workcenter] = $this->workcenterWithShift();

        $response = $this->get("/live/{$workcenter->live_token}");

        $response->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    // ── Weeks ───────────────────────────────────────────────────────────

    public function test_it_returns_the_current_and_the_next_week(): void
    {
        [$workcenter] = $this->workcenterWithShift();

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->has('weeks', 2)
            ->where('weeks.0.weekStart', self::THIS_WEEK)
            ->where('weeks.0.weekNumber', 39)
            ->where('weeks.1.weekStart', self::NEXT_WEEK)
            ->where('weeks.1.weekNumber', 40)
            ->where('today', '2026-09-23'));
    }

    public function test_the_week_changes_on_monday(): void
    {
        [$workcenter] = $this->workcenterWithShift();
        Carbon::setTestNow('2026-09-28 00:05:00');

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.weekStart', self::NEXT_WEEK)
            ->where('weeks.1.weekStart', '2026-10-05'));
    }

    // ── Published state ─────────────────────────────────────────────────

    public function test_an_unpublished_week_carries_no_shifts_and_no_names(): void
    {
        [$workcenter, $shift] = $this->workcenterWithShift();
        $this->assign($workcenter, $shift, '2026-09-22', 'Pat');

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.published', false)
            ->where('weeks.0.shifts', [])
            ->where('weeks.1.published', false));
    }

    public function test_a_week_published_for_another_workcenter_does_not_count(): void
    {
        [$workcenter, $shift] = $this->workcenterWithShift();
        $other = Workcenter::factory()->create();
        $this->publish($other, self::THIS_WEEK);
        $this->assign($workcenter, $shift, '2026-09-22', 'Pat');

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.published', false));
    }

    public function test_each_week_is_published_on_its_own(): void
    {
        [$workcenter] = $this->workcenterWithShift();
        $this->publish($workcenter, self::NEXT_WEEK);

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.published', false)
            ->where('weeks.1.published', true));
    }

    // ── Grid ────────────────────────────────────────────────────────────

    public function test_a_published_week_lists_monday_to_friday_for_each_shift(): void
    {
        [$workcenter] = $this->workcenterWithShift();
        $this->publish($workcenter, self::THIS_WEEK);

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.published', true)
            ->where('weeks.0.days', ['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'])
            ->has('weeks.0.shifts', 1)
            ->where('weeks.0.shifts.0.name', 'Early')
            ->where('weeks.0.shifts.0.start_time', '06:00')
            ->where('weeks.0.shifts.0.end_time', '14:00')
            ->has('weeks.0.shifts.0.cells', 5)
            ->where('weeks.0.shifts.0.cells.0.date', '2026-09-21'));
    }

    public function test_a_cell_lists_full_names_and_the_open_spots(): void
    {
        [$workcenter, $shift] = $this->workcenterWithShift(spots: 3);
        $this->publish($workcenter, self::THIS_WEEK);
        $this->assign($workcenter, $shift, '2026-09-22', 'Sam', 'Zed');
        $this->assign($workcenter, $shift, '2026-09-22', 'Ann', 'Able');

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.shifts.0.cells.1.spots', 3)
            ->where('weeks.0.shifts.0.cells.1.names', ['Ann Able', 'Sam Zed'])
            ->where('weeks.0.shifts.0.cells.1.open', 1)
            ->where('weeks.0.shifts.0.cells.0.names', [])
            ->where('weeks.0.shifts.0.cells.0.open', 3));
    }

    public function test_a_date_override_replaces_the_weekday_capacity(): void
    {
        [$workcenter, $shift] = $this->workcenterWithShift(spots: 2);
        $this->publish($workcenter, self::THIS_WEEK);
        WorkcenterShiftDateOverride::create([
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-23',
            'spots' => 0,
        ]);

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.shifts.0.cells.2.spots', 0)
            ->where('weeks.0.shifts.0.cells.2.open', 0)
            ->where('weeks.0.shifts.0.cells.1.spots', 2));
    }

    public function test_open_spots_never_go_below_zero(): void
    {
        [$workcenter, $shift] = $this->workcenterWithShift(spots: 1);
        $this->publish($workcenter, self::THIS_WEEK);
        $this->assign($workcenter, $shift, '2026-09-21', 'Ann');
        $this->assign($workcenter, $shift, '2026-09-21', 'Bob');

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->has('weeks.0.shifts.0.cells.0.names', 2)
            ->where('weeks.0.shifts.0.cells.0.open', 0));
    }

    public function test_another_workcenters_assignments_do_not_appear(): void
    {
        [$workcenter, $shift] = $this->workcenterWithShift();
        $this->publish($workcenter, self::THIS_WEEK);
        $other = Workcenter::factory()->create();
        $other->shifts()->attach($shift);
        $this->assign($other, $shift, '2026-09-21', 'Elsewhere');

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('weeks.0.shifts.0.cells.0.names', []));
    }

    public function test_shifts_that_are_not_attached_do_not_appear(): void
    {
        [$workcenter] = $this->workcenterWithShift();
        $this->publish($workcenter, self::THIS_WEEK);
        Shift::factory()->create(['name' => 'Night']);

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->has('weeks.0.shifts', 1));
    }

    public function test_it_reports_when_the_data_was_generated(): void
    {
        [$workcenter] = $this->workcenterWithShift();

        $this->get("/live/{$workcenter->live_token}")->assertInertia(fn ($page) => $page
            ->where('generatedAt', Carbon::now()->toIso8601String()));
    }
}
