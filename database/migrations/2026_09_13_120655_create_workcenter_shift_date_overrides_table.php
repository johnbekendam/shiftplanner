<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workcenter_shift_date_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workcenter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('spots');
            $table->timestamps();

            $table->unique(['workcenter_id', 'shift_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workcenter_shift_date_overrides');
    }
};
