<?php

namespace App\Services;

use App\Jobs\SendMailboxMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Mail\Mailable;

class MailboxLogger
{
    public function queue(User $owner, string $email, ?string $name, Mailable $mailable): Message
    {
        $message = Message::create([
            'user_id' => $owner->id,
            'recipient_name' => $name,
            'recipient_email' => $email,
            'subject' => $mailable->envelope()->subject,
            'body_html' => $mailable->render(),
            'status' => 'outbox',
            'sent_at' => null,
        ]);

        SendMailboxMessage::dispatch($message->id, $email, $mailable);

        return $message;
    }
}
