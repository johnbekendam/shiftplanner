<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function setPeriod(string $start, string $end, int $fteHours = 40): void
    {
        PlanningSettings::current()->update([
            'period_start' => $start,
            'period_end' => $end,
            'fte_hours' => $fteHours,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_manager_may_view_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
    }

    public function test_without_a_full_period_the_payload_has_no_data(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('period', null));
    }

    public function test_a_plain_weekday_is_weekly_hours_over_fte_hours(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40); // Monday
        Employee::factory()->create(['weekly_hours' => 32, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('days', ['2026-01-05'])
                ->where('overall.available', [0.8])
            );
    }

    public function test_the_available_series_counts_confirmed_employees_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $line = BusinessLine::factory()->create(['target_fte' => 5]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id, 'confirmed' => true]);
        Employee::factory()->create(['weekly_hours' => 20, 'business_line_id' => $line->id, 'confirmed' => false]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.available', [1])
                ->where('overall.available_hours', 8)
                ->where('lines.0.available', [1])
                ->where('lines.0.available_hours', 8)
            );
    }

    public function test_the_payload_has_no_unconfirmed_or_total_series(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        BusinessLine::factory()->create();

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('overall.available_confirmed')
                ->missing('overall.available_unconfirmed')
                ->missing('overall.available_total')
                ->missing('overall.available_hours_confirmed')
                ->missing('overall.available_hours_unconfirmed')
                ->missing('lines.0.available_confirmed')
                ->missing('lines.0.available_unconfirmed')
                ->missing('lines.0.available_total')
                ->missing('lines.0.available_hours_confirmed')
                ->missing('lines.0.available_hours_unconfirmed')
            );
    }

    public function test_weekend_dates_are_dropped_from_the_charts(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-11', fteHours: 40); // Mon .. Sun
        Employee::factory()->create(['weekly_hours' => 40, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('days', ['2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08', '2026-01-09'])
                ->where('overall.available', [1, 1, 1, 1, 1])
            );
    }

    public function test_a_weekend_only_period_yields_an_empty_payload(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-03', '2026-01-04'); // Sat .. Sun
        BusinessLine::factory()->create(['target_fte' => 5]);
        Employee::factory()->create(['weekly_hours' => 40, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('days', [])
                ->where('overall.available', [])
                ->where('overall.available_hours', 0)
                ->where('overall.required_hours', 0)
            );
    }

    public function test_each_block_carries_available_and_required_hours(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40); // one Monday
        $line = BusinessLine::factory()->create(['target_fte' => 5]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id, 'confirmed' => true]);

        // One weekday, one full-timer: 1.0 FTE * 40 / 5 = 8 available hours.
        // Target 5 FTE * 1 weekday * 40 / 5 = 40 required hours.
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.available_hours', 8)
                ->where('overall.required_hours', 40)
                ->where('lines.0.available_hours', 8)
                ->where('lines.0.required_hours', 40)
            );
    }

    public function test_available_and_required_hours_sum_across_the_weekdays(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-11', fteHours: 40); // 5 weekdays
        $line = BusinessLine::factory()->create(['target_fte' => 5]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id, 'confirmed' => true]);

        // 5 weekdays * 8 = 40 available hours; 5 * 5 * 8 = 200 required hours.
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.available_hours', 40)
                ->where('overall.required_hours', 200)
            );
    }

    public function test_required_hours_is_zero_when_the_target_is_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $line = BusinessLine::factory()->create(['target_fte' => 0]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.required_hours', 0)
                ->where('lines.0.available_hours', 8)
            );
    }

    public function test_a_holiday_day_contributes_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05');
        $employee = Employee::factory()->create(['weekly_hours' => 40, 'confirmed' => true]);
        $employee->holidays()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-10']);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.available', [0]));
    }

    public function test_zero_weekly_hours_contributes_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05');
        Employee::factory()->create(['weekly_hours' => 0, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.available', [0]));
    }

    public function test_the_overall_series_counts_an_employee_with_no_business_line(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $line = BusinessLine::factory()->create(['target_fte' => 5]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id, 'confirmed' => true]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => null, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.available', [2])
                ->where('overall.target', 5)
                ->has('lines', 1)
                ->where('lines.0.available', [1])
                ->where('lines.0.target', 5)
            );
    }

    public function test_each_business_line_gets_its_own_block_in_position_order(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $second = BusinessLine::factory()->create(['abbreviation' => 'VLV', 'position' => 2, 'target_fte' => 3]);
        $first = BusinessLine::factory()->create(['abbreviation' => 'PMP', 'position' => 1, 'target_fte' => 8]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $first->id, 'confirmed' => true]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.target', 11)
                ->where('lines.0.id', $first->id)
                ->where('lines.0.abbreviation', 'PMP')
                ->where('lines.0.available', [1])
                ->where('lines.1.id', $second->id)
                ->where('lines.1.abbreviation', 'VLV')
                ->where('lines.1.available', [0])
            );
    }

    private function assign(Employee $employee, string $date, string $start = '08:00', string $end = '16:00'): void
    {
        ShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => Shift::factory()->create(['start_time' => $start, 'end_time' => $end])->id,
            'date' => $date,
        ]);
    }

    public function test_planned_hours_are_a_weekly_fte_step_across_each_weeks_weekdays(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-16', fteHours: 40); // two full weeks
        $employee = Employee::factory()->create(['confirmed' => true]);
        $this->assign($employee, '2026-01-05'); // Monday, 8 h
        $this->assign($employee, '2026-01-10'); // Saturday, 8 h — weekend shifts count
        $this->assign($employee, '2026-01-13', '12:00', '16:00'); // 4 h in week two

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.planned', [0.4, 0.4, 0.4, 0.4, 0.4, 0.1, 0.1, 0.1, 0.1, 0.1])
            );
    }

    public function test_planned_hours_count_the_full_iso_week_outside_the_period(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-07', '2026-01-09', fteHours: 40); // Wednesday to Friday
        $employee = Employee::factory()->create(['confirmed' => true]);
        $this->assign($employee, '2026-01-05'); // Monday of the same week, before the period
        $this->assign($employee, '2026-01-12'); // next week, outside the chart

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.planned', [0.2, 0.2, 0.2]));
    }

    public function test_planned_hours_split_by_the_employees_business_line(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $line = BusinessLine::factory()->create(['position' => 1]);
        $other = BusinessLine::factory()->create(['position' => 2]);
        $this->assign(Employee::factory()->create(['business_line_id' => $line->id, 'confirmed' => true]), '2026-01-05');
        $this->assign(Employee::factory()->create(['business_line_id' => $line->id, 'confirmed' => false]), '2026-01-06');
        $this->assign(Employee::factory()->create(['business_line_id' => null, 'confirmed' => true]), '2026-01-07');

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.planned', [0.6])
                ->where('lines.0.id', $line->id)
                ->where('lines.0.planned', [0.4])
                ->where('lines.1.id', $other->id)
                ->where('lines.1.planned', [0])
            );
    }

    public function test_a_weekend_only_period_has_an_empty_planned_series(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-10', '2026-01-11', fteHours: 40);
        $this->assign(Employee::factory()->create(), '2026-01-10');

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.planned', []));
    }

    /** Attaches a shift to the workcenter with the same slot count on the given weekdays. */
    private function demand(Workcenter $workcenter, array $weekdays, int $spots, string $start = '08:00', string $end = '16:00'): Shift
    {
        $shift = Shift::factory()->create(['start_time' => $start, 'end_time' => $end]);
        $workcenter->shifts()->attach($shift);
        foreach ($weekdays as $weekday) {
            WorkcenterShiftCapacity::query()->create([
                'workcenter_id' => $workcenter->id,
                'shift_id' => $shift->id,
                'weekday' => $weekday,
                'spots' => $spots,
            ]);
        }

        return $shift;
    }

    public function test_demand_is_a_weekly_fte_step_of_slots_times_shift_hours(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-16', fteHours: 40); // two full weeks
        $workcenter = Workcenter::factory()->create();
        // Monday and Saturday, 2 slots of 8 h: 32 h per week. Weekend demand counts.
        $shift = $this->demand($workcenter, [1, 6], spots: 2);
        // Week two: the Monday override drops to 1 slot, so 24 h.
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => '2026-01-12',
            'spots' => 1,
        ]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.demand', [0.8, 0.8, 0.8, 0.8, 0.8, 0.6, 0.6, 0.6, 0.6, 0.6])
            );
    }

    public function test_demand_sums_every_shift_of_every_active_workcenter(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $this->demand(Workcenter::factory()->create(), [1], spots: 1); // 8 h
        $this->demand(Workcenter::factory()->create(), [1], spots: 3, start: '12:00', end: '16:00'); // 12 h
        $this->demand(Workcenter::factory()->create(['archived_at' => now()]), [1], spots: 5);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.demand', [0.5]));
    }

    public function test_demand_counts_the_full_week_outside_the_period(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-07', '2026-01-09', fteHours: 40); // Wednesday to Friday
        $this->demand(Workcenter::factory()->create(), [1], spots: 1); // Monday, before the period

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.demand', [0.2, 0.2, 0.2]));
    }

    public function test_a_business_line_block_has_no_demand_series(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        BusinessLine::factory()->create();
        $this->demand(Workcenter::factory()->create(), [1], spots: 1);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->missing('lines.0.demand'));
    }

    public function test_a_weekend_only_period_has_an_empty_demand_series(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-10', '2026-01-11', fteHours: 40);
        $this->demand(Workcenter::factory()->create(), [6], spots: 1);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.demand', []));
    }
}
