<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'employees'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // The date of the newest What's new entry the person saw. Null: none yet.
                $table->date('whats_new_seen_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'employees'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('whats_new_seen_at');
            });
        }
    }
};
