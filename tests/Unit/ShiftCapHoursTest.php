<?php

namespace Tests\Unit;

use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The max-hours cap counts a shift at its duration rounded to a 4-hour block. */
class ShiftCapHoursTest extends TestCase
{
    use RefreshDatabase;

    public static function durations(): array
    {
        return [
            '8.25h rounds down to 8' => ['06:00', '14:15', 8.0],
            '9h with dinner break rounds down to 8' => ['14:00', '23:00', 8.0],
            'exact 8h stays 8' => ['08:00', '16:00', 8.0],
            '6h tie rounds up to 8' => ['08:00', '14:00', 8.0],
            '10h tie rounds up to 12' => ['08:00', '18:00', 12.0],
            '11h rounds up to 12' => ['08:00', '19:00', 12.0],
            '5h rounds down to 4' => ['08:00', '13:00', 4.0],
            '1h counts as the 4h minimum' => ['08:00', '09:00', 4.0],
            '2h tie counts as 4' => ['08:00', '10:00', 4.0],
            'overnight 9h rounds to 8' => ['22:00', '07:00', 8.0],
        ];
    }

    /** @dataProvider durations */
    public function test_cap_hours_between_rounds_to_a_four_hour_block(string $start, string $end, float $expected): void
    {
        $this->assertSame($expected, Shift::capHoursBetween($start, $end));
    }

    public function test_the_instance_method_matches_the_static_one(): void
    {
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:15']);

        $this->assertSame(8.0, $shift->capHours());
    }
}
