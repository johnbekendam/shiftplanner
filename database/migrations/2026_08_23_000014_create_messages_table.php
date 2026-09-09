<?php

use App\Enums\MessageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Plain string, not a DB enum: the type set grows and each new
            // case would otherwise need a constraint migration. Validated
            // against App\Enums\MessageType in the app.
            $table->string('type')->default(MessageType::PersonalPageLink->value)->index();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->longText('body_html')->nullable();
            $table->enum('status', ['draft', 'outbox', 'sent'])->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
