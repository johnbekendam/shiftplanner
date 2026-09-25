<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** An alternating pair is now a hard weekly exclusion with no severity. */
    public function up(): void
    {
        DB::table('planning_rules')
            ->where('type', 'alternating_shift_pair')
            ->update(['mode' => 'hard', 'severity' => null]);
    }

    /** The old severity cannot be recovered; every pair comes back at the middle weight. */
    public function down(): void
    {
        DB::table('planning_rules')
            ->where('type', 'alternating_shift_pair')
            ->update(['mode' => 'soft', 'severity' => 5]);
    }
};
