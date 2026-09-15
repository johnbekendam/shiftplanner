<?php

namespace Database\Seeders;

use App\Models\Workcenter;
use Illuminate\Database\Seeder;

class WorkcenterSeeder extends Seeder
{
    private const NAMES = ['Line 1', 'Line 2', 'Line 3'];

    public function run(): void
    {
        $position = 0;

        foreach (self::NAMES as $name) {
            $position++;

            Workcenter::firstOrCreate(
                ['name' => $name],
                ['position' => $position],
            );
        }
    }
}
