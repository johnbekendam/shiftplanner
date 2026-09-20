<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_generation_runs', function (Blueprint $table) {
            $table->id();
            $table->date('cycle_start');
            $table->string('status')->default('pending');
            $table->json('changes')->nullable();
            $table->json('unfulfilled')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('cycle_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_generation_runs');
    }
};
