<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    private const THIS_WEEK = '2026-09-21';

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

    // ── Access and week ─────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/roster')->assertRedirect('/login');
    }

    public function test_a_manager_may_view_the_roster(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/roster')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Roster'));
    }

    public function test_an_admin_may_view_the_roster(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/roster')->assertOk();
    }

    public function test_the_current_week_is_the_default(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/roster')->assertInertia(fn ($page) => $page
            ->where('weekStart', self::THIS_WEEK)
            ->where('weekNumber', 39)
            ->where('today', '2026-09-23')
            ->where('days', [
                '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24',
                '2026-09-25', '2026-09-26', '2026-09-27',
            ]));
    }

    public function test_the_week_query_selects_the_week_of_that_date(): void
    {
        $this->actingAs(User::factory()->create());

        // A Thursday snaps back to its Monday.
        $this->get('/roster?week=2026-10-08')->assertInertia(fn ($page) => $page
            ->where('weekStart', '2026-10-05')
            ->where('weekNumber', 41));
    }

    public function test_an_invalid_week_query_falls_back_to_the_current_week(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/roster?week=nonsense')->assertInertia(fn ($page) => $page
            ->where('weekStart', self::THIS_WEEK));
    }

    // ── Rows ────────────────────────────────────────────────────────────

    private function workcenter(string $name): Workcenter
    {
        return Workcenter::factory()->create(['name' => $name]);
    }

    private function shift(string $name, string $start): Shift
    {
        return Shift::factory()->create(['name' => $name, 'start_time' => $start, 'end_time' => '23:00']);
    }

    private function employee(string $first, string $last = 'Smith', ?BusinessLine $line = null): Employee
    {
        return Employee::factory()->create([
            'first_name' => $first,
            'last_name' => $last,
            'business_line_id' => $line?->id,
        ]);
    }

    private function publish(Workcenter $workcenter, string $weekStart = self::THIS_WEEK): void
    {
        PublishedWeek::create(['week_start' => $weekStart, 'workcenter_id' => $workcenter->id]);
    }

    private function assign(Employee $employee, Workcenter $workcenter, Shift $shift, string $date): void
    {
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => $date,
        ]);
    }

    private function rows(string $query = ''): array
    {
        $this->actingAs(User::factory()->create());

        return $this->get('/roster'.$query)->assertOk()->viewData('page')['props']['rows'];
    }

    public function test_a_row_lists_the_published_assignments_for_each_day(): void
    {
        $line = BusinessLine::factory()->create(['abbreviation' => 'ASM']);
        $assembly = $this->workcenter('Assembly');
        $early = $this->shift('Early', '06:00');
        $anna = $this->employee('Anna', 'Smit', $line);
        $this->publish($assembly);
        $this->assign($anna, $assembly, $early, '2026-09-22');

        $rows = $this->rows();

        $this->assertCount(1, $rows);
        $this->assertSame($anna->id, $rows[0]['id']);
        $this->assertSame('Anna Smit', $rows[0]['name']);
        $this->assertSame('ASM', $rows[0]['business_line']);
        $this->assertCount(7, $rows[0]['days']);
        $this->assertSame([], $rows[0]['days'][0]);
        $this->assertSame([['shift' => 'Early', 'workcenter' => 'Assembly']], $rows[0]['days'][1]);
    }

    public function test_an_employee_with_no_business_line_has_null(): void
    {
        $assembly = $this->workcenter('Assembly');
        $this->publish($assembly);
        $this->assign($this->employee('Anna'), $assembly, $this->shift('Early', '06:00'), '2026-09-22');

        $this->assertNull($this->rows()[0]['business_line']);
    }

    public function test_assignments_of_an_unpublished_workcenter_are_left_out(): void
    {
        $assembly = $this->workcenter('Assembly');
        $packing = $this->workcenter('Packing');
        $early = $this->shift('Early', '06:00');
        $anna = $this->employee('Anna');
        $this->publish($assembly);
        $this->assign($anna, $assembly, $early, '2026-09-22');
        $this->assign($anna, $packing, $early, '2026-09-23');
        $this->assign($this->employee('Bob'), $packing, $early, '2026-09-22');

        $rows = $this->rows();

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]['days'][2]);
    }

    public function test_a_workcenter_published_for_another_week_is_left_out(): void
    {
        $assembly = $this->workcenter('Assembly');
        $this->publish($assembly, '2026-09-28');
        $this->assign($this->employee('Anna'), $assembly, $this->shift('Early', '06:00'), '2026-09-22');

        $this->assertSame([], $this->rows());
    }

    public function test_assignments_outside_the_week_are_left_out(): void
    {
        $assembly = $this->workcenter('Assembly');
        $this->publish($assembly);
        $this->publish($assembly, '2026-09-28');
        $this->assign($this->employee('Anna'), $assembly, $this->shift('Early', '06:00'), '2026-09-28');

        $this->assertSame([], $this->rows());
    }

    public function test_the_selected_week_shows_its_own_assignments(): void
    {
        $assembly = $this->workcenter('Assembly');
        $this->publish($assembly, '2026-09-28');
        $this->assign($this->employee('Anna'), $assembly, $this->shift('Early', '06:00'), '2026-10-04');

        $rows = $this->rows('?week=2026-09-28');

        $this->assertCount(1, $rows);
        $this->assertSame([['shift' => 'Early', 'workcenter' => 'Assembly']], $rows[0]['days'][6]);
    }

    public function test_rows_are_sorted_by_name(): void
    {
        $assembly = $this->workcenter('Assembly');
        $early = $this->shift('Early', '06:00');
        $this->publish($assembly);
        $this->assign($this->employee('Cleo', 'Adams'), $assembly, $early, '2026-09-22');
        $this->assign($this->employee('Anna', 'Zeeman'), $assembly, $early, '2026-09-22');
        $this->assign($this->employee('Anna', 'Berg'), $assembly, $early, '2026-09-22');

        $this->assertSame(
            ['Anna Berg', 'Anna Zeeman', 'Cleo Adams'],
            array_column($this->rows(), 'name'),
        );
    }

    public function test_several_assignments_on_one_day_are_in_shift_start_order(): void
    {
        $assembly = $this->workcenter('Assembly');
        $packing = $this->workcenter('Packing');
        $late = $this->shift('Late', '14:00');
        $early = $this->shift('Early', '06:00');
        $anna = $this->employee('Anna');
        $this->publish($assembly);
        $this->publish($packing);
        $this->assign($anna, $packing, $late, '2026-09-22');
        $this->assign($anna, $assembly, $early, '2026-09-22');

        $this->assertSame([
            ['shift' => 'Early', 'workcenter' => 'Assembly'],
            ['shift' => 'Late', 'workcenter' => 'Packing'],
        ], $this->rows()[0]['days'][1]);
    }
}
