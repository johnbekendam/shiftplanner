<?php

namespace Tests\Feature;

use App\Models\PlanningSettings;
use App\Services\Planning\PlanningCycle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_no_period_start_is_configured(): void
    {
        $this->assertNull(PlanningCycle::containing(Carbon::parse('2026-09-10')));
    }

    public function test_the_anchor_date_itself_starts_its_own_cycle(): void
    {
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);

        $this->assertSame('2026-09-07', PlanningCycle::containing(Carbon::parse('2026-09-07'))->toDateString());
    }

    public function test_the_second_week_of_a_cycle_still_resolves_to_its_start(): void
    {
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);

        $this->assertSame('2026-09-07', PlanningCycle::containing(Carbon::parse('2026-09-13'))->toDateString());
    }

    public function test_the_next_cycle_starts_two_weeks_later(): void
    {
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);

        $this->assertSame('2026-09-21', PlanningCycle::containing(Carbon::parse('2026-09-21'))->toDateString());
        $this->assertSame('2026-09-21', PlanningCycle::containing(Carbon::parse('2026-09-27'))->toDateString());
    }

    public function test_a_date_before_the_anchor_resolves_to_the_preceding_cycle(): void
    {
        PlanningSettings::current()->update(['period_start' => '2026-09-07']);

        $this->assertSame('2026-08-24', PlanningCycle::containing(Carbon::parse('2026-08-31'))->toDateString());
    }

    public function test_the_anchor_snaps_to_the_monday_of_its_own_week(): void
    {
        // A Thursday period_start — its cycle still starts on the preceding Monday.
        PlanningSettings::current()->update(['period_start' => '2026-09-10']);

        $this->assertSame('2026-09-07', PlanningCycle::containing(Carbon::parse('2026-09-07'))->toDateString());
    }

    public function test_all_within_period_is_empty_when_the_period_is_not_fully_configured(): void
    {
        $this->assertSame([], PlanningCycle::allWithinPeriod());

        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => null]);
        $this->assertSame([], PlanningCycle::allWithinPeriod());
    }

    public function test_all_within_period_includes_every_cycle_that_starts_on_or_before_the_period_end(): void
    {
        // A cycle starting within the period runs in full even though its own end
        // (10-04) extends past period_end (09-25) — cycles aren't truncated.
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-09-25']);

        $this->assertSame(
            ['2026-09-07', '2026-09-21'],
            array_map(fn ($c) => $c->toDateString(), PlanningCycle::allWithinPeriod()),
        );
    }

    public function test_all_within_period_excludes_a_cycle_that_starts_after_the_period_end(): void
    {
        PlanningSettings::current()->update(['period_start' => '2026-09-07', 'period_end' => '2026-09-20']);

        $this->assertSame(
            ['2026-09-07'],
            array_map(fn ($c) => $c->toDateString(), PlanningCycle::allWithinPeriod()),
        );
    }

    public function test_all_within_period_snaps_to_the_monday_of_the_anchor_week(): void
    {
        PlanningSettings::current()->update(['period_start' => '2026-09-10', 'period_end' => '2026-09-10']);

        $this->assertSame(
            ['2026-09-07'],
            array_map(fn ($c) => $c->toDateString(), PlanningCycle::allWithinPeriod()),
        );
    }
}
