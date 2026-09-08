<?php

namespace App\Http\Controllers;

use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\Message;
use App\Services\MessageComposer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MailboxController extends Controller
{
    private const TABS = ['compose', 'draft', 'outbox', 'sent'];

    public function __construct(private MessageComposer $composer) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'draft');
        abort_unless(in_array($tab, self::TABS, true), 404);

        $userId = $request->user()->id;
        $counts = [
            'draft' => Message::forUser($userId)->forStatus('draft')->count(),
            'outbox' => Message::forUser($userId)->forStatus('outbox')->count(),
            'sent' => Message::forUser($userId)->forStatus('sent')->count(),
        ];

        $search = $request->input('search', '');
        $messages = null;

        if ($tab !== 'compose') {
            $query = Message::forUser($userId)->forStatus($tab);
            if ($search !== '') {
                $query->search($search);
            }
            $messages = $query->latest()->paginate(20)->withQueryString();
        }

        return Inertia::render('Mailbox', [
            'messages' => $messages,
            'tab' => $tab,
            'search' => $search,
            'counts' => $counts,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        return response()->json($this->composer->renderForRecipient($data['subject'], $data['body']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'string'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'send_mode' => ['required', 'in:draft,queue'],
        ]);

        $emails = $this->parseRecipients($data['to']);
        $user = $request->user();
        $status = $data['send_mode'] === 'queue' ? 'outbox' : 'draft';
        $bodyFragment = $this->composer->render($data['subject'], $data['body'])['body_html'];

        foreach ($emails as $email) {
            $mailable = new ComposedMessage($data['subject'], $bodyFragment, $user->email, $user->name);

            $message = Message::create([
                'user_id' => $user->id,
                'recipient_email' => $email,
                'subject' => $data['subject'],
                'body' => $data['body'],
                'body_html' => $mailable->render(),
                'status' => $status,
            ]);

            if ($status === 'outbox') {
                SendMailboxMessage::dispatch($message->id, $email, $mailable);
            }
        }

        return redirect()->back()->with('success', __('mailbox.flash.'.$data['send_mode']));
    }

    public function send(Request $request, Message $message)
    {
        abort_unless($message->user_id === $request->user()->id, 403);
        abort_unless($message->status === 'draft', 422);

        $owner = $message->user;
        $fragment = $this->composer->render($message->subject, $message->body ?? '')['body_html'];
        $mailable = new ComposedMessage($message->subject, $fragment, $owner->email, $owner->name);

        $message->update(['status' => 'outbox', 'body_html' => $mailable->render()]);

        SendMailboxMessage::dispatch($message->id, $message->recipient_email, $mailable);

        return redirect()->back()->with('success', __('mailbox.flash.queue'));
    }

    public function destroy(Request $request, Message $message)
    {
        abort_unless($message->user_id === $request->user()->id, 403);

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

        $query = Message::forUser($request->user()->id)->forStatus($data['tab']);

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } elseif (! empty($data['search'])) {
            $query->search($data['search']);
        }

        $count = $query->delete();

        return redirect()->back()->with('success', __('mailbox.flash.bulk_deleted', ['count' => $count]));
    }

    private function parseRecipients(string $to): array
    {
        $emails = collect(explode(',', $to))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            throw ValidationException::withMessages(['to' => __('mailbox.compose.error.no_recipients')]);
        }

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['to' => __('mailbox.compose.error.invalid_email', ['email' => $email])]);
            }
        }

        return $emails->all();
    }
}
