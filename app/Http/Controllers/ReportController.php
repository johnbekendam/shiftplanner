<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Services\UninformedPlanning;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Sortable Workcenter-report columns. `mode` only applies to the For-workcenter table. */
    private const WORKCENTER_SORT_KEYS = ['name', 'business_line', 'weekly_hours'];

    private const FOR_WORKCENTER_SORT_KEYS = [...self::WORKCENTER_SORT_KEYS, 'mode'];

    /** Sortable Planned-hours-report columns. */
    private const PLANNED_HOURS_SORT_KEYS = ['workcenter', 'date', 'hours'];

    public function __construct(private UninformedPlanning $planning) {}

    public function index(Request $request)
    {
        $shiftId = $request->integer('shift') ?: null;
        $businessLineId = $request->integer('business_line') ?: null;
        $includeUnconfirmed = $request->boolean('unconfirmed', true);
        $planningBusinessLineId = $request->integer('planning_business_line') ?: null;
        $competenceMode = $request->string('competence_mode')->toString() === 'has' ? 'has' : 'missing';
        $competenceId = $request->integer('competence') ?: $this->legacyCompetenceId($request);
        $selectedCompetence = $competenceId
            ? Competence::query()->whereKey($competenceId)->first(['id', 'name'])
            : null;
        $workcenterMode = $request->string('workcenter_mode')->toString() === 'for_workcenter' ? 'for_workcenter' : 'unassigned';
        $workcenterId = $request->integer('workcenter') ?: null;
        $selectedWorkcenter = $workcenterId
            ? Workcenter::query()->whereKey($workcenterId)->first(['id', 'name'])
            : null;
        $workcenterSortKeys = $workcenterMode === 'for_workcenter' ? self::FOR_WORKCENTER_SORT_KEYS : self::WORKCENTER_SORT_KEYS;
        $workcenterSort = $request->input('workcenter_sort');
        $workcenterSort = in_array($workcenterSort, $workcenterSortKeys, true) ? $workcenterSort : 'name';
        $workcenterDirection = $request->input('workcenter_direction') === 'desc' ? 'desc' : 'asc';
        [$plannedHoursFrom, $plannedHoursTo] = $this->plannedHoursRange($request);
        $plannedHoursSort = $request->input('planned_hours_sort');
        $plannedHoursSort = in_array($plannedHoursSort, self::PLANNED_HOURS_SORT_KEYS, true) ? $plannedHoursSort : 'date';
        $plannedHoursDirection = $request->input('planned_hours_direction') === 'desc' ? 'desc' : 'asc';

        return Inertia::render('Reports/Index', [
            'employees' => $this->missingAvailability($shiftId, $businessLineId, $includeUnconfirmed),
            'uninformedPlanning' => $this->planning->summary($planningBusinessLineId)->all(),
            'competenceReport' => $this->competenceReport($competenceMode, $selectedCompetence),
            'unassignedWorkcenterReport' => $this->unassignedWorkcenterReport($workcenterSort, $workcenterDirection),
            'workcenterReport' => $this->workcenterReport($selectedWorkcenter, $workcenterSort, $workcenterDirection),
            'plannedHoursReport' => $this->plannedHoursReport($plannedHoursFrom, $plannedHoursTo, $plannedHoursSort, $plannedHoursDirection),
            'shifts' => Shift::all()->map->toPayload()->all(),
            'businessLines' => BusinessLine::all()->map->toPayload()->all(),
            'competences' => Competence::all()->map->toPayload()->all(),
            'workcenters' => Workcenter::query()->whereNull('archived_at')->get(['id', 'name'])
                ->map(fn (Workcenter $workcenter) => ['id' => $workcenter->id, 'name' => $workcenter->name])
                ->all(),
            'filters' => [
                'shift' => $shiftId,
                'business_line' => $businessLineId,
                'unconfirmed' => $includeUnconfirmed,
                'planning_business_line' => $planningBusinessLineId,
                'competence_mode' => $competenceMode,
                'competence_id' => $selectedCompetence?->id,
                'workcenter_mode' => $workcenterMode,
                'workcenter_id' => $selectedWorkcenter?->id,
                'workcenter_sort' => $workcenterSort,
                'workcenter_direction' => $workcenterDirection,
                'planned_hours_from' => $plannedHoursFrom->toDateString(),
                'planned_hours_to' => $plannedHoursTo->toDateString(),
                'planned_hours_sort' => $plannedHoursSort,
                'planned_hours_direction' => $plannedHoursDirection,
            ],
        ]);
    }

    /**
     * Streams the planned-hours report as an .xlsx, covering the full result set (no pagination).
     * Dates and hours are typed cells, so Excel shows them in the viewer's own locale.
     */
    public function exportPlannedHours(Request $request): StreamedResponse
    {
        [$from, $to] = $this->plannedHoursRange($request);
        $rows = $this->sortPlannedHours($this->plannedHoursRows($from, $to), 'date', 'asc');

        return response()->streamDownload(function () use ($rows) {
            $writer = new XlsxWriter;
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName(__('reports.tab.planned_hours'));
            $writer->addRow(Row::fromValues([
                __('reports.planned_hours.column.workcenter'),
                __('reports.planned_hours.column.date'),
                __('reports.planned_hours.column.hours'),
            ]));
            $dateStyle = new Style(format: 'yyyy-mm-dd');
            $hoursStyle = new Style(format: '0.00');
            foreach ($rows as $row) {
                $writer->addRow(new Row([
                    Cell::fromValue($row['workcenter']),
                    Cell::fromValue(new DateTimeImmutable($row['date']), $dateStyle),
                    Cell::fromValue($row['hours'], $hoursStyle),
                ]));
            }
            $writer->close();
        }, 'planned-hours.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** The planned-hours date range from the request, defaulting to the current week (Monday to Sunday). */
    private function plannedHoursRange(Request $request): array
    {
        $from = $request->filled('planned_hours_from')
            ? Carbon::parse($request->string('planned_hours_from')->toString())->startOfDay()
            : now()->startOfWeek(Carbon::MONDAY);
        $to = $request->filled('planned_hours_to')
            ? Carbon::parse($request->string('planned_hours_to')->toString())->startOfDay()
            : now()->endOfWeek(Carbon::SUNDAY);

        return [$from, $to];
    }

    private function legacyCompetenceId(Request $request): ?int
    {
        return collect($request->input('competences', []))
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->first(fn ($id) => $id > 0);
    }

    /**
     * Employees with no recurring-availability row for the given shift, or,
     * with no shift picked ("All shifts"), employees with no row at all.
     */
    private function missingAvailability(?int $shiftId, ?int $businessLineId, bool $includeUnconfirmed): array
    {
        return Employee::query()
            ->where('weekly_hours', '>', 0)
            ->when(!$includeUnconfirmed, fn ($q) => $q->where('confirmed', true))
            ->when($businessLineId !== null, fn ($q) => $q->where('business_line_id', $businessLineId))
            ->whereDoesntHave(
                'recurringAvailabilities',
                fn ($q) => $q->when($shiftId !== null, fn ($q) => $q->where('shift_id', $shiftId)),
            )
            ->with('businessLine')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'has_email' => $employee->email !== null,
                'business_line' => $employee->businessLine?->abbreviation,
                'weekly_hours' => $employee->weekly_hours,
                'confirmed' => $employee->confirmed,
            ])
            ->all();
    }

    private function competenceReport(string $mode, ?Competence $selectedCompetence): array
    {
        if ($selectedCompetence === null) {
            return [];
        }

        return Employee::query()
            ->with('competences:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (Employee $employee) use ($mode, $selectedCompetence) {
                $hasCompetence = $employee->competences->contains('id', $selectedCompetence->id);

                if ($mode === 'has') {
                    if (!$hasCompetence) {
                        return null;
                    }

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'competence_names' => [$selectedCompetence->name],
                    ];
                }

                if ($hasCompetence) {
                    return null;
                }

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'competence_names' => [$selectedCompetence->name],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** Confirmed employees with no employee_workcenter row at all, hard or soft. 15 per page, sortable. */
    private function unassignedWorkcenterReport(string $sort, string $direction)
    {
        $query = Employee::query()
            ->where('confirmed', true)
            ->whereDoesntHave('workcenters')
            ->with('businessLine');

        $this->applyWorkcenterSort($query, in_array($sort, self::WORKCENTER_SORT_KEYS, true) ? $sort : 'name', $direction);

        return $query->paginate(15, ['*'], 'workcenter_page')
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'business_line' => $employee->businessLine?->abbreviation,
                'weekly_hours' => $employee->weekly_hours,
            ]);
    }

    /** Confirmed employees holding a hard or soft row for the picked workcenter. 15 per page, sortable. */
    private function workcenterReport(?Workcenter $selectedWorkcenter, string $sort, string $direction)
    {
        if ($selectedWorkcenter === null) {
            return ['data' => [], 'links' => [], 'from' => null, 'to' => null, 'total' => 0, 'last_page' => 1];
        }

        $query = $selectedWorkcenter->employees()->where('confirmed', true)->with('businessLine');

        $this->applyWorkcenterSort($query, in_array($sort, self::FOR_WORKCENTER_SORT_KEYS, true) ? $sort : 'name', $direction);

        return $query->paginate(15, ['*'], 'workcenter_page')
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'mode' => $employee->pivot->mode,
                'business_line' => $employee->businessLine?->abbreviation,
                'weekly_hours' => $employee->weekly_hours,
            ]);
    }

    /** Shared Name/Business line/Weekly hours(/Mode) sort for both workcenter-report tables. */
    private function applyWorkcenterSort($query, string $sort, string $direction): void
    {
        match ($sort) {
            'business_line' => $query->orderBy(
                BusinessLine::select('abbreviation')->whereColumn('business_lines.id', 'employees.business_line_id'),
                $direction,
            ),
            'weekly_hours' => $query->orderBy('employees.weekly_hours', $direction),
            'mode' => $query->orderBy('employee_workcenter.mode', $direction),
            default => $query->orderBy('employees.first_name', $direction)->orderBy('employees.last_name', $direction),
        };

        if ($sort !== 'name') {
            $query->orderBy('employees.first_name')->orderBy('employees.last_name');
        }
    }

    /** Published planned hours per active workcenter per day within the range. 15 per page, sortable. */
    private function plannedHoursReport(Carbon $from, Carbon $to, string $sort, string $direction): LengthAwarePaginator
    {
        $rows = $this->sortPlannedHours($this->plannedHoursRows($from, $to), $sort, $direction);
        $page = Paginator::resolveCurrentPage('planned_hours_page');
        $perPage = 15;

        return (new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'planned_hours_page'],
        ))->withQueryString();
    }

    /**
     * One `{ workcenter, date, hours }` row per active workcenter and day with published
     * planned hours in the range. Every employee's assignments count, confirmed or not.
     * Draft (unpublished) assignments and zero-hour combinations are left out.
     *
     * @return Collection<int, array{workcenter: string, date: string, hours: float}>
     */
    private function plannedHoursRows(Carbon $from, Carbon $to): Collection
    {
        $activeWorkcenterIds = Workcenter::query()->whereNull('archived_at')->pluck('id');

        $assignments = ShiftAssignment::query()
            ->whereIn('workcenter_id', $activeWorkcenterIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['workcenter:id,name', 'shift:id,start_time,end_time'])
            ->get();

        $weekKey = fn (ShiftAssignment $a) => $a->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $publishedPairs = PublishedWeek::lockedPairs(
            $from->copy()->startOfWeek(Carbon::MONDAY),
            $to->copy()->endOfWeek(Carbon::SUNDAY),
            $activeWorkcenterIds,
        );

        return $assignments
            ->filter(fn (ShiftAssignment $a) => $publishedPairs->has("{$weekKey($a)}:{$a->workcenter_id}"))
            ->groupBy(fn (ShiftAssignment $a) => "{$a->workcenter_id}:{$a->date->toDateString()}")
            ->map(fn (Collection $group) => [
                'workcenter' => $group->first()->workcenter->name,
                'date' => $group->first()->date->toDateString(),
                'hours' => round($group->sum(fn (ShiftAssignment $a) => $a->shift->durationHours()), 2),
            ])
            ->values();
    }

    /** Sorts computed planned-hours rows by Workcenter/Date/Hours, with a stable date-then-workcenter tiebreaker. */
    private function sortPlannedHours(Collection $rows, string $sort, string $direction): Collection
    {
        return $rows->sort(function (array $a, array $b) use ($sort, $direction) {
            $primary = match ($sort) {
                'workcenter' => $a['workcenter'] <=> $b['workcenter'],
                'hours' => $a['hours'] <=> $b['hours'],
                default => $a['date'] <=> $b['date'],
            };
            $primary = $direction === 'desc' ? -$primary : $primary;

            if ($primary !== 0) {
                return $primary;
            }

            return $sort === 'workcenter'
                ? $a['date'] <=> $b['date']
                : $a['workcenter'] <=> $b['workcenter'];
        })->values();
    }
}
