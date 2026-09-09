<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            $table->text('shift_note')->nullable()->after('period_end');
        });
    }

    public function down(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            $table->dropColumn('shift_note');
        });
    }
};
