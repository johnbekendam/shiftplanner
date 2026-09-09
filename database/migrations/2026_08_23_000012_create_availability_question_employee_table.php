<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_question_employee', function (Blueprint $table) {
            $table->foreignId('availability_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->primary(['availability_question_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_question_employee');
    }
};
