<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_lines', function (Blueprint $table) {
            $table->id();
            $table->string('abbreviation', 10)->unique();
            $table->string('description');
            $table->decimal('target_fte', 5, 1)->default(0);
            $table->unsignedInteger('position'); // manual order, low to high
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_lines');
    }
};
