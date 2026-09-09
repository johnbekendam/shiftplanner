<?php

namespace Database\Seeders;

use App\Models\BusinessLine;
use Illuminate\Database\Seeder;

class BusinessLineSeeder extends Seeder
{
    /** Abbreviation => [description, target FTE]. */
    private const LINES = [
        'PC' => ['Power Conversion', 10],
        'ECS' => ['Embedded Computer Systems', 10],
        'MFS' => ['Manufacturing Services', 2],
        'PEBLAR' => ['Peblar', 2],
        'PS' => ['Precision Solutions', 5],
        'PE' => ['Facility / Production Engineering', 3],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::LINES as $abbreviation => [$description, $targetFte]) {
            $position++;

            BusinessLine::firstOrCreate(
                ['abbreviation' => $abbreviation],
                [
                    'description' => $description,
                    'target_fte' => $targetFte,
                    'position' => $position,
                ],
            );
        }
    }
}
