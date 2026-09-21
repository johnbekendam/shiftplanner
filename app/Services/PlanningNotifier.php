<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Queues a Planning email for every employee with uninformed planning, from
 * the saved Planning template (features/planning-notifications/). Each
 * message records the shifts it lists, so the send job can mark them
 * informed once the email is really sent.
 */
class PlanningNotifier
{
    public function __construct(
        private UninformedPlanning $planning,
        private MessagePlaceholders $placeholders,
        private MessageComposer $composer,
    ) {}

    /**
     * @return int the number of emails queued
     *
     * @throws ValidationException when there is nobody to send to, or the template cannot list shifts
     */
    public function queueForUninformed(User $sender): int
    {
        $rows = $this->planning->summary(excludeQueued: true, reachableOnly: true);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['planning' => __('planning.error.nothing_to_send')]);
        }

        $template = MessageTemplate::forType(MessageType::Planning);

        if (! str_contains($template->body, ':planning')) {
            throw ValidationException::withMessages(['planning' => __('planning.error.template_without_planning')]);
        }

        $queued = 0;

        foreach (Employee::whereIn('id', $rows->pluck('id'))->get() as $employee) {
            $resolved = $this->placeholders->resolve($template->subject, $template->body, $employee);

            if ($resolved['unresolved'] !== []) {
                continue;
            }

            $fragment = $this->composer->render($resolved['subject'], $resolved['body'])['body_html'];

            $message = Message::create([
                'user_id' => $sender->id,
                'type' => MessageType::Planning,
                'recipient_email' => $employee->email,
                'recipient_name' => $employee->name,
                'subject' => $resolved['subject'],
                'body' => $resolved['body'],
                'body_html' => (new ComposedMessage($resolved['subject'], $fragment, logoSrc: ComposedMessage::browserLogoUrl()))->render(),
                'assignment_ids' => $this->planning->upcomingFor($employee)->pluck('id')->all(),
                'status' => 'outbox',
            ]);

            SendMailboxMessage::dispatch(
                $message->id,
                $employee->email,
                new ComposedMessage($resolved['subject'], $fragment, $sender->email, $sender->name),
            );

            $queued++;
        }

        return $queued;
    }
}
