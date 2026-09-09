<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            // On by default: today the personal page is always editable.
            $table->boolean('allow_employee_changes')->default(true)->after('shift_note');
        });
    }

    public function down(): void
    {
        Schema::table('planning_settings', function (Blueprint $table) {
            $table->dropColumn('allow_employee_changes');
        });
    }
};
