<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competence_employee', function (Blueprint $table) {
            $table->foreignId('competence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->primary(['competence_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competence_employee');
    }
};
