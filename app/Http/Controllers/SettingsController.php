<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityQuestion;
use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\PlanningSettings;
use App\Models\Shift;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'competences' => $this->listWithHolderCount(Competence::query()),
            'businessLines' => $this->businessLines(),
            'shifts' => Shift::all()->map->toPayload()->all(),
            'workcenters' => $this->workcenters(),
            'shiftNote' => PlanningSettings::current()->shift_note ?? '',
            'scheduleNote' => PlanningSettings::current()->shift_schedule_note ?? '',
            'questions' => $this->questions(),
            'period' => PlanningSettings::current()->toPayload(),
        ]);
    }

    private function listWithHolderCount($query): array
    {
        return $query
            ->withCount('employees')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'position' => $row->position,
                'holder_count' => $row->employees_count,
            ])
            ->all();
    }

    /** Questions for the config list: name is the text, plus the yes-answer count. */
    private function questions(): array
    {
        return AvailabilityQuestion::query()
            ->withCount('employees')
            ->get()
            ->map(fn (AvailabilityQuestion $question) => [
                'id' => $question->id,
                'name' => $question->text,
                'position' => $question->position,
                'holder_count' => $question->employees_count,
            ])
            ->all();
    }

    private function businessLines(): array
    {
        return BusinessLine::query()
            ->withCount('employees')
            ->get()
            ->map(fn (BusinessLine $line) => [
                ...$line->toPayload(),
                'employee_count' => $line->employees_count,
            ])
            ->all();
    }

    private function workcenters(): array
    {
        return Workcenter::all()
            ->map(fn (Workcenter $workcenter) => [
                ...$workcenter->toPayload(),
                'shifts' => $workcenter->shifts->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'weekday_capacities' => $this->weekdayCapacities($workcenter, $shift),
                ])->all(),
            ])
            ->all();
    }

    /** Spots for weekdays 1 (Monday) through 7 (Sunday), 0 where no row exists yet. */
    private function weekdayCapacities(Workcenter $workcenter, Shift $shift): array
    {
        $capacities = WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->pluck('spots', 'weekday');

        return collect(range(1, 7))->map(fn ($weekday) => $capacities->get($weekday, 0))->all();
    }
}
