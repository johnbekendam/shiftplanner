<?php

namespace Tests\Unit;

use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftDurationHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_same_day_shift_returns_the_correct_hour_count(): void
    {
        $shift = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '16:00']);

        $this->assertSame(8.0, $shift->durationHours());
    }

    public function test_an_overnight_shift_wraps_past_midnight(): void
    {
        $shift = Shift::factory()->create(['start_time' => '22:00', 'end_time' => '06:00']);

        $this->assertSame(8.0, $shift->durationHours());
    }

    public function test_duration_hours_between_matches_the_instance_method(): void
    {
        $this->assertSame(4.5, Shift::durationHoursBetween('09:00', '13:30'));
    }
}
