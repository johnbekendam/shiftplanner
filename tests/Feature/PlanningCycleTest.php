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
}
