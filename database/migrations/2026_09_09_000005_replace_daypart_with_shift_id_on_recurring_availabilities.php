<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recurring availability grid now has one row per defined shift, not
 * per abstract daypart. Prototype data is synthetic, so no rows are
 * carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_availabilities', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'weekday', 'daypart']);
            $table->dropColumn('daypart');
        });

        Schema::table('recurring_availabilities', function (Blueprint $table) {
            $table->foreignId('shift_id')->after('weekday')->constrained()->cascadeOnDelete();
            $table->unique(['employee_id', 'weekday', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('recurring_availabilities', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'weekday', 'shift_id']);
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::table('recurring_availabilities', function (Blueprint $table) {
            $table->string('daypart')->after('weekday');
            $table->unique(['employee_id', 'weekday', 'daypart']);
        });
    }
};
