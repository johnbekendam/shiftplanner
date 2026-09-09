<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1 (Monday) to 5 (Friday); weekday-only grid
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('level'); // not_preferred | unavailable
            $table->timestamps();

            $table->unique(['employee_id', 'weekday', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_availabilities');
    }
};
