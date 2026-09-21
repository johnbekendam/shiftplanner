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
use App\Models\Workcenter;
use App\Services\EmployeePersonalLinkService;
use App\Services\MessageComposer;
use App\Services\PersonalLinkMessage;
use App\Services\PlannedShifts;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct(private EmployeePersonalLinkService $links, private PlannedShifts $plannedShifts) {}

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

        $businessLines = BusinessLine::all(); // position-ordered by the model scope

        $requestedBusinessLines = $request->query('business_lines');
        $businessLineFilter = is_array($requestedBusinessLines)
            ? array_values(array_intersect($requestedBusinessLines, [...$businessLines->pluck('id')->map(strval(...)), 'none']))
            : null;

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

        $filterIds = [];
        $filterIncludesNone = false;

        if ($businessLineFilter !== null) {
            $filterIds = array_map('intval', array_values(array_filter($businessLineFilter, fn ($id) => $id !== 'none')));
            $filterIncludesNone = in_array('none', $businessLineFilter, true);

            $query->where(function ($q) use ($filterIds, $filterIncludesNone) {
                if ($filterIds !== []) $q->orWhereIn('employees.business_line_id', $filterIds);
                if ($filterIncludesNone) $q->orWhereNull('employees.business_line_id');
                if ($filterIds === [] && ! $filterIncludesNone) $q->whereRaw('1 = 0');
            });
        }

        $selectedBusinessLines = $businessLineFilter !== null
            ? [...$filterIds, ...($filterIncludesNone ? ['none'] : [])]
            : [...$businessLines->pluck('id')->all(), 'none'];

        $employees = $query->paginate(15)->withQueryString();

        $shifts = Shift::all();

        $employees = $employees->through(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'has_email' => $employee->email !== null,
            'business_line' => $employee->businessLine?->abbreviation,
            'weekly_hours' => $employee->weekly_hours,
            'confirmed' => $employee->confirmed,
            'shift_coverage' => $this->shiftCoverage($employee, $shifts),
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'businessLines' => $businessLines->map(fn (BusinessLine $line) => [
                'id' => $line->id,
                'abbreviation' => $line->abbreviation,
            ])->all(),
            'selectedBusinessLines' => $selectedBusinessLines,
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
        $shifts = Shift::all();
        $visibleShifts = $shifts->where('visible_by_default', true);
        $heldWorkcenterIds = $employee->workcenters()->pluck('workcenters.id');
        $workcenters = Workcenter::query()
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhereIn('id', $heldWorkcenterIds))
            ->get();

        return Inertia::render('Employees/Form', [
            'employee' => [
                ...$employee->only(['id', 'first_name', 'last_name', 'email', 'weekly_hours', 'weekly_hours_minimum', 'business_line_id']),
                'link_sent' => $employee->email !== null && Message::query()
                    ->where('type', MessageType::PersonalPageLink)
                    ->where('status', 'sent')
                    ->where('recipient_email', $employee->email)
                    ->exists(),
            ],
            'weeklyHoursMinimum' => $employee->effectiveWeeklyHoursMinimum(),
            'globalWeeklyHoursMinimum' => PlanningSettings::current()->weekly_hours_minimum,
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
            'holidays' => $employee->holidays->map->toPayload()->all(),
            'shifts' => $visibleShifts->map->toPayload()->values()->all(),
            'shiftNoteHtml' => PlanningSettings::current()->shiftNoteHtml($employee->first_name),
            'scheduleNoteHtml' => PlanningSettings::current()->scheduleNoteHtml($employee->first_name),
            'availability' => $employee->recurringAvailabilities
                ->whereIn('shift_id', $visibleShifts->pluck('id'))
                ->map->toPayload()->values()->all(),
            'competences' => Competence::all()->map->toPayload()->all(),
            'competenceIds' => $employee->competences->pluck('id')->all(),
            'workcenters' => $workcenters->map(fn (Workcenter $workcenter) => [
                'id' => $workcenter->id,
                'name' => $workcenter->name,
                'archived' => $workcenter->archived_at !== null,
            ])->values()->all(),
            'employeeWorkcenterAssignments' => $employee->workcenters->map(fn (Workcenter $workcenter) => [
                'workcenter_id' => $workcenter->id,
                'mode' => $workcenter->pivot->mode,
            ])->values()->all(),
            'questions' => AvailabilityQuestion::all()->map->toPayload()->all(),
            'questionAnswers' => $employee->availabilityQuestions->pluck('id')->all(),
            'plannedShifts' => $this->plannedShifts->forEmployee($employee, publishedOnly: false),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $employee->fill($data);
        $employee->save();

        return redirect("/employees/{$employee->id}/edit")->with('success', __('employees.flash.updated'));
    }

    public function updateConfirmed(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'confirmed' => ['required', 'boolean'],
        ]);

        $employee->update(['confirmed' => $data['confirmed']]);

        return redirect()->back()->with('success', __('employees.flash.updated'));
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

    /** Single-employee delete from the edit page. Same cascade as bulkDelete. */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect('/employees')->with('success', __('employees.flash.deleted_one'));
    }

    private function shiftCoverage(Employee $employee, Collection $shifts): array
    {
        $covered = $employee->recurringAvailabilities
            ->whereIn('level', ['available', 'not_preferred'])
            ->whereIn('weekday', self::WEEKDAYS)
            ->groupBy('shift_id');

        return $shifts
            ->filter(fn (Shift $shift) => $shift->visible_by_default)
            ->map(fn (Shift $shift) => [
                'shift_id' => $shift->id,
                'name' => $shift->name,
                'coverage_percentage' => (int) round(
                    (($covered->get($shift->id)?->count() ?? 0) / count(self::WEEKDAYS)) * 100
                ),
            ])
            ->values()
            ->all();
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
        if ($employee->email === null) {
            return back()->with('error', __('employees.flash.link_no_email'));
        }

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
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'weekly_hours' => ['required', 'integer', 'min:0', 'max:48'],
            'business_line_id' => ['nullable', 'integer', 'exists:business_lines,id'],
        ];

        if ($employee !== null) {
            $rules += [
                'weekly_hours_minimum' => ['nullable', 'integer', 'min:1', 'max:48'],
            ];
        }

        $data = $request->validate($rules);

        $this->assertUniqueNameWithoutEmail($data, $employee);

        return $data;
    }

    /**
     * Without an email, the name is the only identity an employee has, so two
     * employees without an email cannot share a first and last name
     * (case-insensitive).
     */
    private function assertUniqueNameWithoutEmail(array $data, ?Employee $employee): void
    {
        $email = array_key_exists('email', $data) ? $data['email'] : $employee?->email;

        if ($email !== null) {
            return;
        }

        $taken = Employee::query()
            ->whereNull('email')
            ->whereRaw('lower(first_name) = ?', [mb_strtolower($data['first_name'])])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower($data['last_name'])])
            ->when($employee !== null, fn ($query) => $query->whereKeyNot($employee->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['email' => __('employees.error.duplicate_name_without_email')]);
        }
    }
}
