<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_weeks', function (Blueprint $table) {
            // Lets the next Generate run fill this published pair's open spots.
            // Cleared after that run (features/autoplanner-published-weeks/).
            $table->boolean('planner_open')->default(false)->after('workcenter_id');
        });
    }

    public function down(): void
    {
        Schema::table('published_weeks', function (Blueprint $table) {
            $table->dropColumn('planner_open');
        });
    }
};
