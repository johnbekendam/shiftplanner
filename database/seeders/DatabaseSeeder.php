<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SeedUserSeeder::class,
            //BusinessLineSeeder::class,
            //ShiftSeeder::class,
            //WorkcenterSeeder::class,
            //CompetenceSeeder::class,
            //AvailabilityQuestionSeeder::class,
            //PlanningSettingsSeeder::class,
            //EmployeeSeeder::class,
        ]);
    }
}
