<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            $table->string('max_hours_per_week_mode')->default('hard');
            $table->unsignedTinyInteger('max_hours_per_week_severity')->nullable();
            $table->unsignedInteger('max_shifts_per_day')->default(1);
            $table->string('max_shifts_per_day_mode')->default('hard');
            $table->unsignedTinyInteger('max_shifts_per_day_severity')->nullable();
            $table->string('not_preferred_shift_mode')->default('soft');
            $table->unsignedTinyInteger('not_preferred_shift_severity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            $table->dropColumn([
                'max_hours_per_week_mode',
                'max_hours_per_week_severity',
                'max_shifts_per_day',
                'max_shifts_per_day_mode',
                'max_shifts_per_day_severity',
                'not_preferred_shift_mode',
                'not_preferred_shift_severity',
            ]);
        });
    }
};
