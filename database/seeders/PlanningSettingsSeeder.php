<?php

namespace Database\Seeders;

use App\Models\PlanningSettings;
use Illuminate\Database\Seeder;

class PlanningSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = PlanningSettings::current();

        $settings->fill([
            'fte_hours' => 40,
            'period_start' => '2026-10-01',
            'period_end' => '2026-12-31',
        ])->save();
    }
}
