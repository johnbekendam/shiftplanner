<?php

namespace Database\Seeders;

use App\Models\Competence;
use Illuminate\Database\Seeder;

class CompetenceSeeder extends Seeder
{
    private const COMPETENCES = [
        'Educational background - Electronics',
        'Educational background - Mechanics',
        'Educational background - Electromechanics',
        'Experience in Wiring and Cable Harness design.',
        'IPC 620 - Acceptance Cable and Wire Harness Assemblies',
        'Experience with Zuken E3',
    ];

    public function run(): void
    {
        foreach (self::COMPETENCES as $position => $name) {
            Competence::firstOrCreate(
                ['name' => $name],
                ['position' => $position + 1],
            );
        }
    }
}
