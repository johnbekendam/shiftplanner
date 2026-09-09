<?php

use App\Enums\MessageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Plain string, not a DB enum: the type set grows and each new
            // case would otherwise need a constraint migration. Validated
            // against App\Enums\MessageType in the app. Existing rows (none
            // in production) take the default.
            $table->string('type')->default(MessageType::PersonalPageLink->value)->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
