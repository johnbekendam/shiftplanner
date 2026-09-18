<?php

namespace Tests\Unit;

use App\Models\PublishedWeek;
use App\Models\Workcenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedWeekLockedPairsTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_a_published_pair_within_the_range(): void
    {
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $workcenter->id]);

        $pairs = PublishedWeek::lockedPairs(
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-20'),
            collect([$workcenter->id]),
        );

        $this->assertTrue($pairs->has("2026-09-07:{$workcenter->id}"));
    }

    public function test_excludes_a_published_week_starting_before_the_range(): void
    {
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-08-24', 'workcenter_id' => $workcenter->id]);

        $pairs = PublishedWeek::lockedPairs(
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-20'),
            collect([$workcenter->id]),
        );

        $this->assertSame(0, $pairs->count());
    }

    public function test_excludes_a_published_week_starting_after_the_range(): void
    {
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-21', 'workcenter_id' => $workcenter->id]);

        $pairs = PublishedWeek::lockedPairs(
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-20'),
            collect([$workcenter->id]),
        );

        $this->assertSame(0, $pairs->count());
    }

    public function test_excludes_a_workcenter_not_in_the_given_list(): void
    {
        $included = Workcenter::factory()->create();
        $excluded = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $excluded->id]);

        $pairs = PublishedWeek::lockedPairs(
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-20'),
            collect([$included->id]),
        );

        $this->assertSame(0, $pairs->count());
    }

    public function test_keeps_workcenters_independent(): void
    {
        $published = Workcenter::factory()->create();
        $unpublished = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-07', 'workcenter_id' => $published->id]);

        $pairs = PublishedWeek::lockedPairs(
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-20'),
            collect([$published->id, $unpublished->id]),
        );

        $this->assertTrue($pairs->has("2026-09-07:{$published->id}"));
        $this->assertFalse($pairs->has("2026-09-07:{$unpublished->id}"));
    }
}
