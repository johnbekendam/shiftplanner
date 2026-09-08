<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('manager')->after('email'); // admin | manager
            $table->string('password')->nullable()->change();
            $table->foreignId('employee_id')->nullable()->unique()->after('is_active')
                ->constrained()->nullOnDelete();
        });

        $seedEmail = config('auth.seed_user.email');

        if ($seedEmail) {
            DB::table('users')->where('email', $seedEmail)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->string('password')->nullable(false)->change();
            $table->dropColumn('role');
        });
    }
};
