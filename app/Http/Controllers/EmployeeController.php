<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\Message;
use App\Models\PlanningSettings;
use App\Models\Shift;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    /** Sortable list columns mapped to their ORDER BY expression. */
    private const SORT_COLUMNS = [
        'name' => 'employees.name',
        'business_line' => 'business_lines.abbreviation',
        'weekly_hours' => 'employees.weekly_hours',
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $sort = $request->input('sort');
        $sort = array_key_exists($sort, self::SORT_COLUMNS) ? $sort : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $query = Employee::query()
            ->select('employees.*')
            ->leftJoin('business_lines', 'business_lines.id', '=', 'employees.business_line_id')
            ->with('businessLine')
            ->orderBy(self::SORT_COLUMNS[$sort], $direction)
            ->orderBy('employees.name');

        if ($search !== '') {
            $query->search($search);
        }

        $employees = $query->paginate(20)->withQueryString();

        $linkSent = Message::query()
            ->where('type', MessageType::PersonalPageLink)
            ->where('status', 'sent')
            ->whereIn('recipient_email', $employees->pluck('email'))
            ->pluck('recipient_email')
            ->flip();

        $employees = $employees->through(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'business_line' => $employee->businessLine?->abbreviation,
            'weekly_hours' => $employee->weekly_hours,
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
        Employee::create($this->validated($request));

        return redirect('/employees')->with('success', __('employees.flash.created'));
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('Employees/Form', [
            'employee' => [
                ...$employee->only(['id', 'name', 'email', 'weekly_hours', 'business_line_id']),
                'link_sent' => Message::query()
                    ->where('type', MessageType::PersonalPageLink)
                    ->where('status', 'sent')
                    ->where('recipient_email', $employee->email)
                    ->exists(),
            ],
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
            'holidays' => $employee->holidays->map->toPayload()->all(),
            'shifts' => Shift::all()->map->toPayload()->all(),
            'shiftNoteHtml' => PlanningSettings::current()->shiftNoteHtml($employee->name),
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

        return redirect('/employees')->with('success', __('employees.flash.updated'));
    }

    public function personalPage(Employee $employee)
    {
        return response()->json(['url' => $this->links->linkFor($employee)]);
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'weekly_hours' => ['required', 'integer', Rule::in(Employee::WEEKLY_HOURS_OPTIONS)],
            'business_line_id' => ['nullable', 'integer', 'exists:business_lines,id'],
        ]);
    }
}
