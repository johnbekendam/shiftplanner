<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            // Free-text note shown directly below the weekly availability
            // grid — shift-timing facts the grid structure cannot carry.
            $table->text('shift_schedule_note')->nullable()->after('shift_note');
        });
    }

    public function down(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            $table->dropColumn('shift_schedule_note');
        });
    }
};
