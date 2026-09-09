<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\User;
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
        Employee::factory()->create(['weekly_hours' => 32]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('days', ['2026-01-05'])
                ->where('overall.available', [0.8])
            );
    }

    public function test_weekend_dates_are_dropped_from_the_charts(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-11', fteHours: 40); // Mon .. Sun
        Employee::factory()->create(['weekly_hours' => 40]);

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
        Employee::factory()->create(['weekly_hours' => 40]);

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
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id]);

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
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id]);

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
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id]);

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
        $employee = Employee::factory()->create(['weekly_hours' => 40]);
        $employee->holidays()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-10']);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.available', [0]));
    }

    public function test_zero_weekly_hours_contributes_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05');
        Employee::factory()->create(['weekly_hours' => 0]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('overall.available', [0]));
    }

    public function test_the_overall_series_counts_an_employee_with_no_business_line(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setPeriod('2026-01-05', '2026-01-05', fteHours: 40);
        $line = BusinessLine::factory()->create(['target_fte' => 5]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $line->id]);
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => null]);

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
        Employee::factory()->create(['weekly_hours' => 40, 'business_line_id' => $first->id]);

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('overall.target', 11)
                ->where('lines.0.abbreviation', 'PMP')
                ->where('lines.0.available', [1])
                ->where('lines.1.abbreviation', 'VLV')
                ->where('lines.1.available', [0])
            );
    }
}
