<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('visible_by_default')->default(true);
        });

        Schema::table('planning_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_hours_minimum')->default(20);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_hours_minimum')->nullable();
        });

        Schema::create('employee_shift_visibility_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->boolean('visible');
            $table->timestamps();

            $table->unique(['employee_id', 'shift_id'], 'employee_shift_visibility_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shift_visibility_overrides');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('weekly_hours_minimum');
        });

        Schema::table('planning_settings', function (Blueprint $table) {
            $table->dropColumn('weekly_hours_minimum');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('visible_by_default');
        });
    }
};