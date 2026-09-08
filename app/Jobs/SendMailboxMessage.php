<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMailboxMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 300, 900, 1800];

    public function __construct(
        private readonly int $messageId,
        private readonly string $email,
        private readonly Mailable $mailable,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send($this->mailable);

        Message::whereKey($this->messageId)->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
