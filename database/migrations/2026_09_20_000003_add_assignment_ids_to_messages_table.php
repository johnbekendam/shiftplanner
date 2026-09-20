<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // The shift assignments a Planning message lists. The send job
            // marks them informed once the email is really sent.
            $table->json('assignment_ids')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('assignment_ids');
        });
    }
};
