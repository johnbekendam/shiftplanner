<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fte_hours')->default(40); // weekly hours that equal one FTE
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('shift_note')->nullable();
            // On by default: today the personal page is always editable.
            $table->boolean('allow_employee_changes')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_settings');
    }
};
