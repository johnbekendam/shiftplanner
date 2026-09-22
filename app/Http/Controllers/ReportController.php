<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Workcenter;
use App\Services\UninformedPlanning;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
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

        return Inertia::render('Reports/Index', [
            'employees' => $this->missingAvailability($shiftId, $businessLineId, $includeUnconfirmed),
            'uninformedPlanning' => $this->planning->summary($planningBusinessLineId)->all(),
            'competenceReport' => $this->competenceReport($competenceMode, $selectedCompetence),
            'unassignedWorkcenterReport' => $this->unassignedWorkcenterReport(),
            'workcenterReport' => $this->workcenterReport($selectedWorkcenter),
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
            ],
        ]);
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

    /** Employees with no employee_workcenter row at all, hard or soft. */
    private function unassignedWorkcenterReport(): array
    {
        return Employee::query()
            ->whereDoesntHave('workcenters')
            ->with('businessLine')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'business_line' => $employee->businessLine?->abbreviation,
                'weekly_hours' => $employee->weekly_hours,
                'confirmed' => $employee->confirmed,
            ])
            ->all();
    }

    private function workcenterReport(?Workcenter $selectedWorkcenter): array
    {
        if ($selectedWorkcenter === null) {
            return [];
        }

        return $selectedWorkcenter->employees()
            ->with('businessLine')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'mode' => $employee->pivot->mode,
                'business_line' => $employee->businessLine?->abbreviation,
                'weekly_hours' => $employee->weekly_hours,
                'confirmed' => $employee->confirmed,
            ])
            ->all();
    }
}
