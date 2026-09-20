<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('published_weeks')->truncate();

        Schema::table('published_weeks', function (Blueprint $table) {
            $table->dropUnique(['week_start']);
            $table->foreignId('workcenter_id')->after('week_start')->constrained()->cascadeOnDelete();
            $table->unique(['week_start', 'workcenter_id']);
        });
    }

    public function down(): void
    {
        Schema::table('published_weeks', function (Blueprint $table) {
            $table->dropUnique(['week_start', 'workcenter_id']);
            $table->dropConstrainedForeignId('workcenter_id');
            $table->unique('week_start');
        });
    }
};
