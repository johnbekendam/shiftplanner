<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_questions', function (Blueprint $table) {
            $table->id();
            $table->string('text')->unique(); // the yes/no question, case-insensitive unique in the app
            $table->unsignedInteger('position'); // manual order, low to high
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_questions');
    }
};
