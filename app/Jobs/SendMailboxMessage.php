<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\ShiftAssignment;
use DateTimeInterface;
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

        $sentAt = now();

        Message::whereKey($this->messageId)->update([
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);

        $this->markListedAssignmentsInformed($sentAt);
    }

    /**
     * A Planning message lists shift assignments (features/planning-notifications/).
     * Once it is really sent, those assignments count as informed. An earlier
     * informed time stays as it is.
     */
    private function markListedAssignmentsInformed(DateTimeInterface $sentAt): void
    {
        $ids = Message::whereKey($this->messageId)->value('assignment_ids');

        if (empty($ids)) {
            return;
        }

        ShiftAssignment::query()
            ->whereIn('id', $ids)
            ->whereNull('informed_at')
            ->update(['informed_at' => $sentAt]);
    }
}
