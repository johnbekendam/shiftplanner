<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('employee_shift_visibility_overrides');
    }

    public function down(): void
    {
        Schema::create('employee_shift_visibility_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->boolean('visible');
            $table->timestamps();

            $table->unique(['employee_id', 'shift_id'], 'employee_shift_visibility_unique');
        });
    }
};