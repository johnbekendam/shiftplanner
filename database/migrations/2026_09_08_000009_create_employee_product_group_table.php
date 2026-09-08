<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_product_group', function (Blueprint $table) {
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_group_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_product_group');
    }
};
