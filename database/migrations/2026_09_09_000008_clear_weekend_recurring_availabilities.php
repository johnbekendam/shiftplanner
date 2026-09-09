<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The recurring availability grid is weekday-only now. Drop any Saturday
 * or Sunday rows left from when it had seven columns. Prototype data is
 * synthetic, so there is no way — and no need — to restore them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('recurring_availabilities')->whereIn('weekday', [6, 7])->delete();
    }

    public function down(): void
    {
        // No-op: the removed rows cannot be reconstructed.
    }
};
