<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('manager'); // admin | manager
            $table->string('password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        $seedEmail = config('auth.seed_user.email');

        if ($seedEmail) {
            DB::table('users')->where('email', $seedEmail)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
