<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workcenters', function (Blueprint $table) {
            $table->string('live_token', 40)->nullable()->unique()->after('responsible');
        });

        DB::table('workcenters')->pluck('id')->each(fn ($id) => DB::table('workcenters')
            ->where('id', $id)
            ->update(['live_token' => Str::random(40)]));
    }

    public function down(): void
    {
        Schema::table('workcenters', function (Blueprint $table) {
            $table->dropColumn('live_token');
        });
    }
};
