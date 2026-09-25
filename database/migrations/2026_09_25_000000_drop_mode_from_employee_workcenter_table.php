<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Each row, hard or soft, stays as a plain membership. */
    public function up(): void
    {
        Schema::table('employee_workcenter', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }

    /** The hard/soft split cannot be recovered; every row comes back as hard. */
    public function down(): void
    {
        Schema::table('employee_workcenter', function (Blueprint $table) {
            $table->string('mode')->default('hard');
        });
    }
};
