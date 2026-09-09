<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Services\MessageComposer;
use App\Services\PersonalLinkMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MailboxController extends Controller
{
    private const TABS = ['compose', 'draft', 'outbox', 'sent'];

    public function __construct(
        private MessageComposer $composer,
        private PersonalLinkMessage $placeholders,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'draft');
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
                'composed_by' => $message->user?->name,
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

    /** Data for the Compose tab: the type list, the current type's template, and the employee list. */
    private function composePayload(Request $request): array
    {
        $type = MessageType::tryFrom((string) $request->input('type')) ?? MessageType::PersonalPageLink;

        return [
            'types' => collect(MessageType::cases())
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
            'preselected_employee_id' => $request->integer('employee') ?: null,
        ];
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(MessageType::class)],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $map = empty($data['employee_id'])
            ? $this->placeholders->sample()
            : $this->placeholders->forEmployee(Employee::findOrFail($data['employee_id']));

        return response()->json($this->composer->renderForRecipient(
            $this->placeholders->apply($data['subject'], $map),
            $this->placeholders->apply($data['body'], $map),
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(MessageType::class)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'send_mode' => ['required', 'in:draft,queue'],
        ]);

        $type = MessageType::from($data['type']);
        $user = $request->user();
        $status = $data['send_mode'] === 'queue' ? 'outbox' : 'draft';
        $employees = Employee::whereIn('id', $data['employee_ids'])->get();

        foreach ($employees as $employee) {
            $map = $this->placeholders->forEmployee($employee);
            $subject = $this->placeholders->apply($data['subject'], $map);
            $body = $this->placeholders->apply($data['body'], $map);
            $fragment = $this->composer->render($subject, $body)['body_html'];
            $mailable = new ComposedMessage($subject, $fragment, $user->email, $user->name);

            $message = Message::create([
                'user_id' => $user->id,
                'type' => $type,
                'recipient_email' => $employee->email,
                'recipient_name' => $employee->name,
                'subject' => $subject,
                'body' => $body,
                'body_html' => $mailable->render(),
                'status' => $status,
            ]);

            if ($status === 'outbox') {
                SendMailboxMessage::dispatch($message->id, $employee->email, $mailable);
            }
        }

        $key = $status === 'outbox' ? 'mailbox.flash.queued' : 'mailbox.flash.drafts_created';

        return redirect()->back()->with('success', __($key, ['count' => $employees->count()]));
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

        $message->update(['status' => 'outbox', 'body_html' => $mailable->render()]);

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
