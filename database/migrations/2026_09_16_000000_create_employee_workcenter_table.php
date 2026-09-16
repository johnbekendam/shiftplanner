<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_workcenter', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workcenter_id')->constrained()->cascadeOnDelete();
            $table->string('mode');

            $table->primary(['employee_id', 'workcenter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_workcenter');
    }
};
