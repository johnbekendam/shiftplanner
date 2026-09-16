<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\MessageComposer;
use App\Services\MessagePlaceholders;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MailboxController extends Controller
{
    private const TABS = ['compose', 'draft', 'outbox', 'sent'];

    public function __construct(
        private MessageComposer $composer,
        private MessagePlaceholders $placeholders,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'compose');
        abort_unless(in_array($tab, self::TABS, true), 404);

        // Shared across all admins: no per-user filter. user_id stays on the
        // row only as "composed by".
        $counts = [
            'draft' => Message::forStatus('draft')->count(),
            'outbox' => Message::forStatus('outbox')->count(),
            'sent' => Message::forStatus('sent')->count(),
        ];

        $search = $request->input('search', '');
        $messages = null;

        if ($tab !== 'compose') {
            $query = Message::query()->with('user:id,name')->forStatus($tab);
            if ($search !== '') {
                $query->search($search);
            }
            $messages = $query->latest()->paginate(20)->withQueryString()->through(fn (Message $message) => [
                'id' => $message->id,
                'recipient_name' => $message->recipient_name,
                'recipient_email' => $message->recipient_email,
                'subject' => $message->subject,
                'status' => $message->status,
                'body_html' => $message->body_html,
                'composed_by' => $message->user?->name ?? __('mailbox.compose.self_signup'),
            ]);
        }

        return Inertia::render('Mailbox', [
            'messages' => $messages,
            'tab' => $tab,
            'search' => $search,
            'counts' => $counts,
            'compose' => $tab === 'compose' ? $this->composePayload($request) : null,
        ]);
    }

    /** Data for the Compose tab: the type list, the current type's template, and the recipient sources. */
    private function composePayload(Request $request): array
    {
        $type = MessageType::tryFrom((string) $request->input('type')) ?? MessageType::Custom;

        return [
            'types' => collect(MessageType::cases())
                ->filter(fn (MessageType $case) => $case->composable())
                ->map(fn (MessageType $case) => [
                    'value' => $case->value,
                    'label' => __($case->langKey().'.label'),
                ])
                ->all(),
            'type' => $type->value,
            'template' => MessageTemplate::forType($type)->only('subject', 'body'),
            'employees' => Employee::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                ])
                ->all(),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
            'preselected_employee_id' => $request->integer('employee') ?: null,
            'unresolved_recipients' => session('unresolved_recipients'),
            'placeholder_tokens' => $this->placeholders->tokens(),
        ];
    }

    /**
     * The union of the given employees and users, deduplicated by email
     * (case-insensitive). An Employee and a User sharing an email count
     * once, keeping the Employee copy so :link still resolves.
     *
     * @return Collection<int, Employee|User>
     */
    private function resolveRecipients(array $employeeIds, array $userIds): Collection
    {
        $employees = Employee::whereIn('id', $employeeIds)->get();
        $seenEmails = $employees->map(fn (Employee $employee) => Str::lower($employee->email))->all();

        $users = User::whereIn('id', $userIds)->get()
            ->reject(fn (User $user) => in_array(Str::lower($user->email), $seenEmails, true));

        return $employees->concat($users);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(MessageType::class)],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $recipient = ! empty($data['employee_id'])
            ? Employee::findOrFail($data['employee_id'])
            : (! empty($data['user_id']) ? User::findOrFail($data['user_id']) : null);

        if ($recipient === null) {
            $map = $this->placeholders->sample();
            $subject = $this->placeholders->apply($data['subject'], $map);
            $body = $this->placeholders->apply($data['body'], $map);
        } else {
            $resolved = $this->placeholders->resolve($data['subject'], $data['body'], $recipient);
            $subject = $resolved['subject'];
            $body = $resolved['body'];
        }

        return response()->json($this->composer->renderForRecipient($subject, $body));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(MessageType::class)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'employee_ids' => ['array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'send_mode' => ['required', 'in:draft,queue'],
            'exclude_unresolved' => ['boolean'],
        ]);

        $employeeIds = $data['employee_ids'] ?? [];
        $userIds = $data['user_ids'] ?? [];

        if (empty($employeeIds) && empty($userIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => __('mailbox.compose.recipients_required'),
            ]);
        }

        $type = MessageType::from($data['type']);
        $user = $request->user();
        $status = $data['send_mode'] === 'queue' ? 'outbox' : 'draft';

        $resolutions = $this->resolveRecipients($employeeIds, $userIds)
            ->map(fn (Employee|User $recipient) => [
                'recipient' => $recipient,
                'result' => $this->placeholders->resolve($data['subject'], $data['body'], $recipient),
            ]);

        $unresolved = $resolutions->filter(fn (array $entry) => $entry['result']['unresolved'] !== []);

        if ($unresolved->isNotEmpty() && ! $request->boolean('exclude_unresolved')) {
            return redirect()->back()->with('unresolved_recipients', $unresolved
                ->map(fn (array $entry) => [
                    'name' => $entry['recipient']->name,
                    'email' => $entry['recipient']->email,
                    'tokens' => $entry['result']['unresolved'],
                ])
                ->values()
                ->all());
        }

        $sendable = $resolutions->reject(fn (array $entry) => $entry['result']['unresolved'] !== []);

        foreach ($sendable as $entry) {
            $recipient = $entry['recipient'];
            $subject = $entry['result']['subject'];
            $body = $entry['result']['body'];
            $fragment = $this->composer->render($subject, $body)['body_html'];
            $mailable = new ComposedMessage($subject, $fragment, $user->email, $user->name);

            $message = Message::create([
                'user_id' => $user->id,
                'type' => $type,
                'recipient_email' => $recipient->email,
                'recipient_name' => $recipient->name,
                'subject' => $subject,
                'body' => $body,
                'body_html' => new ComposedMessage($subject, $fragment, logoSrc: ComposedMessage::browserLogoUrl())->render(),
                'status' => $status,
            ]);

            if ($status === 'outbox') {
                SendMailboxMessage::dispatch($message->id, $recipient->email, $mailable);
            }
        }

        $key = $status === 'outbox' ? 'mailbox.flash.queued' : 'mailbox.flash.drafts_created';

        return redirect()->back()->with('success', __($key, ['count' => $sendable->count()]));
    }

    public function updateTemplate(Request $request, MessageType $type)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        MessageTemplate::forType($type)->update($data);

        return redirect()->back()->with('success', __('mailbox.flash.template_saved'));
    }

    public function send(Request $request, Message $message)
    {
        abort_unless($message->status === 'draft', 422);

        $owner = $message->user;
        $fragment = $this->composer->render($message->subject, $message->body ?? '')['body_html'];
        $mailable = new ComposedMessage($message->subject, $fragment, $owner?->email, $owner?->name);

        $message->update([
            'status' => 'outbox',
            'body_html' => new ComposedMessage($message->subject, $fragment, logoSrc: ComposedMessage::browserLogoUrl())->render(),
        ]);

        SendMailboxMessage::dispatch($message->id, $message->recipient_email, $mailable);

        return redirect()->back()->with('success', __('mailbox.flash.queued', ['count' => 1]));
    }

    public function destroy(Request $request, Message $message)
    {
        $message->delete();

        return redirect()->back()->with('success', __('mailbox.flash.deleted'));
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'tab' => ['required', 'in:draft,outbox,sent'],
            'search' => ['nullable', 'string'],
            'ids' => ['array'],
            'ids.*' => ['integer'],
        ]);

        $query = Message::forStatus($data['tab']);

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } elseif (! empty($data['search'])) {
            $query->search($data['search']);
        }

        $count = $query->delete();

        return redirect()->back()->with('success', __('mailbox.flash.bulk_deleted', ['count' => $count]));
    }
}
