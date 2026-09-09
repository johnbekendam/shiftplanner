<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Handles a public self-signup request: find or create the employee for
 * the given email, then send the personal-page link through the mailbox
 * pipeline. See doc/features/employee-self-signup/spec.md.
 */
class SelfSignupService
{
    /** One accepted request per email inside this window. */
    public const REQUEST_MAX = 1;

    public const REQUEST_WINDOW_SECONDS = 600;

    public function __construct(
        private PersonalLinkMessage $placeholders,
        private MessageComposer $composer,
    ) {}

    /**
     * Match an employee by email (case-insensitive), create one when none
     * matches, then queue the personal-page link to that address. An
     * existing employee's stored details are left untouched. Silent once
     * the per-email request limit is spent.
     */
    public function register(string $firstName, string $lastName, string $email): void
    {
        $email = trim($email);
        $key = 'self-signup:'.Str::lower($email);

        if (RateLimiter::tooManyAttempts($key, self::REQUEST_MAX)) {
            return;
        }

        RateLimiter::hit($key, self::REQUEST_WINDOW_SECONDS);

        $employee = Employee::query()
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->first()
            ?? Employee::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
            ]);

        $this->send($employee);
    }

    /** Build the outbox Message from the stored template and dispatch it. */
    private function send(Employee $employee): void
    {
        $template = MessageTemplate::forType(MessageType::PersonalPageLink);
        $map = $this->placeholders->forEmployee($employee);
        $subject = $this->placeholders->apply($template->subject, $map);
        $body = $this->placeholders->apply($template->body, $map);
        $fragment = $this->composer->render($subject, $body)['body_html'];
        $mailable = new ComposedMessage($subject, $fragment);

        $message = Message::create([
            'user_id' => null,
            'type' => MessageType::PersonalPageLink,
            'recipient_email' => $employee->email,
            'recipient_name' => $employee->name,
            'subject' => $subject,
            'body' => $body,
            'body_html' => $mailable->render(),
            'status' => 'outbox',
        ]);

        SendMailboxMessage::dispatch($message->id, $employee->email, $mailable);
    }
}
