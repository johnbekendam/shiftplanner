<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /** Name => [start, end], wall-clock. */
    private const SHIFTS = [
        'Morning' => ['06:00', '14:15'],
        'Evening' => ['14:15', '23:00'],
    ];

    public function run(): void
    {
        foreach (self::SHIFTS as $name => [$start, $end]) {
            Shift::firstOrCreate(
                ['name' => $name],
                ['start_time' => $start, 'end_time' => $end],
            );
        }
    }
}
