<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Jobs\SendMailboxMessage;
use App\Mail\ComposedMessage;
use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PlanningSettings;
use App\Models\Shift;
use App\Services\EmployeePersonalLinkService;
use App\Services\MessageComposer;
use App\Services\PersonalLinkMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    /** Sortable list columns mapped to their ORDER BY expression(s). */
    private const SORT_COLUMNS = [
        'name' => ['employees.first_name', 'employees.last_name'],
        'business_line' => ['business_lines.abbreviation'],
        'weekly_hours' => ['employees.weekly_hours'],
    ];

    private const WEEKDAYS = [1, 2, 3, 4, 5];

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $sort = $request->input('sort');
        $sort = array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $query = Employee::query()
            ->select('employees.*')
            ->leftJoin('business_lines', 'business_lines.id', '=', 'employees.business_line_id')
            ->with(['businessLine', 'recurringAvailabilities']);

        foreach (self::SORT_COLUMNS[$sort] as $column) {
            $query->orderBy($column, $direction);
        }

        if ($sort !== 'name') {
            $query->orderBy('employees.first_name')->orderBy('employees.last_name');
        }

        if ($search !== '') {
            $query->search($search);
        }

        $employees = $query->paginate(15)->withQueryString();

        $linkSent = Message::query()
            ->where('type', MessageType::PersonalPageLink)
            ->where('status', 'sent')
            ->whereIn('recipient_email', $employees->pluck('email'))
            ->pluck('recipient_email')
            ->flip();

        $shifts = Shift::all();

        $employees = $employees->through(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'business_line' => $employee->businessLine?->abbreviation,
            'weekly_hours' => $employee->weekly_hours,
            'shift_coverage' => $this->shiftCoverage($employee, $shifts),
            'link_sent' => $linkSent->has($employee->email),
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create()
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::create($this->validated($request));

        return redirect("/employees/{$employee->id}/edit")->with('success', __('employees.flash.created'));
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('Employees/Form', [
            'employee' => [
                ...$employee->only(['id', 'first_name', 'last_name', 'email', 'weekly_hours', 'business_line_id']),
                'link_sent' => Message::query()
                    ->where('type', MessageType::PersonalPageLink)
                    ->where('status', 'sent')
                    ->where('recipient_email', $employee->email)
                    ->exists(),
            ],
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
            'holidays' => $employee->holidays->map->toPayload()->all(),
            'shifts' => Shift::all()->map->toPayload()->all(),
            'shiftNoteHtml' => PlanningSettings::current()->shiftNoteHtml($employee->first_name),
            'scheduleNoteHtml' => PlanningSettings::current()->scheduleNoteHtml($employee->first_name),
            'availability' => $employee->recurringAvailabilities->map->toPayload()->all(),
            'competences' => Competence::all()->map->toPayload()->all(),
            'competenceIds' => $employee->competences->pluck('id')->all(),
            'questions' => AvailabilityQuestion::all()->map->toPayload()->all(),
            'questionAnswers' => $employee->availabilityQuestions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));

        return redirect("/employees/{$employee->id}/edit")->with('success', __('employees.flash.updated'));
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ]);

        $count = Employee::query()->whereKey($data['ids'])->delete();

        return redirect()->back()->with('success', __('employees.flash.deleted', ['count' => $count]));
    }

    private function shiftCoverage(Employee $employee, Collection $shifts): array
    {
        $unavailable = $employee->recurringAvailabilities
            ->where('level', 'unavailable')
            ->whereIn('weekday', self::WEEKDAYS)
            ->groupBy('shift_id');

        return $shifts->map(fn (Shift $shift) => [
            'shift_id' => $shift->id,
            'name' => $shift->name,
            'coverage_percentage' => (int) round(
                ((count(self::WEEKDAYS) - ($unavailable->get($shift->id)?->count() ?? 0)) / count(self::WEEKDAYS)) * 100
            ),
        ])->all();
    }

    public function personalPage(Employee $employee)
    {
        return response()->json(['url' => $this->links->linkFor($employee)]);
    }

    /**
     * Queue the personal-page-link message for one employee straight to
     * the outbox — same rendering and delivery the Compose tab uses, just
     * without the manual form. Open to any signed-in user, like the rest
     * of `/employees` — unlike the mailbox itself, which stays admin-only.
     */
    public function sendLink(Request $request, Employee $employee, PersonalLinkMessage $placeholders, MessageComposer $composer)
    {
        $template = MessageTemplate::forType(MessageType::PersonalPageLink);
        $map = $placeholders->forEmployee($employee);
        $subject = $placeholders->apply($template->subject, $map);
        $body = $placeholders->apply($template->body, $map);
        $fragment = $composer->render($subject, $body)['body_html'];
        $user = $request->user();
        $mailable = new ComposedMessage($subject, $fragment, $user->email, $user->name);

        $message = Message::create([
            'user_id' => $user->id,
            'type' => MessageType::PersonalPageLink,
            'recipient_email' => $employee->email,
            'recipient_name' => $employee->name,
            'subject' => $subject,
            'body' => $body,
            'body_html' => new ComposedMessage($subject, $fragment, logoSrc: ComposedMessage::browserLogoUrl())->render(),
            'status' => 'outbox',
        ]);

        SendMailboxMessage::dispatch($message->id, $employee->email, $mailable);

        return back()->with('success', __('employees.flash.link_queued'));
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'weekly_hours' => ['required', 'integer', Rule::in(Employee::WEEKLY_HOURS_OPTIONS)],
            'business_line_id' => ['nullable', 'integer', 'exists:business_lines,id'],
        ]);
    }
}
