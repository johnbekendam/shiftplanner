<?php

namespace Tests\Feature;

use App\Models\PlanningRule;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlternatingShiftPairMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_makes_existing_pairs_hard_without_severity(): void
    {
        $first = Shift::factory()->create();
        $second = Shift::factory()->create();
        $pair = PlanningRule::create([
            'type' => 'alternating_shift_pair',
            'mode' => 'soft',
            'severity' => 7,
            'config' => ['first_shift_id' => $first->id, 'second_shift_id' => $second->id],
        ]);
        $other = PlanningRule::create([
            'type' => 'max_shifts_per_day',
            'mode' => 'soft',
            'severity' => 4,
            'config' => ['value' => 1],
        ]);

        $migration = require base_path('database/migrations/2026_09_25_000001_make_alternating_shift_pairs_hard.php');
        $migration->up();

        $pair->refresh();
        $this->assertSame('hard', $pair->mode);
        $this->assertNull($pair->severity);

        $other->refresh();
        $this->assertSame('soft', $other->mode);
        $this->assertSame(4, $other->severity);
    }
}
