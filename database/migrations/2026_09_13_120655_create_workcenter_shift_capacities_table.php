<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workcenter_shift_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workcenter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1 (Monday) to 7 (Sunday)
            $table->unsignedInteger('spots')->default(0);
            $table->timestamps();

            $table->unique(['workcenter_id', 'shift_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workcenter_shift_capacities');
    }
};
