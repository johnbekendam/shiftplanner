<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product groups were dropped from the product. The tables and their
 * create migrations (2026_09_08_000008 / _000009) stay in history; this
 * removes the tables from any database that already ran them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('employee_product_group');
        Schema::dropIfExists('product_groups');
    }

    public function down(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('position');
            $table->timestamps();
        });

        Schema::create('employee_product_group', function (Blueprint $table) {
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_group_id', 'employee_id']);
        });
    }
};
