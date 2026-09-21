<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Models\Employee;
use App\Models\Message;
use App\Models\PublishedWeek;
use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds published planning an employee has not been told about yet. Only a
 * published shift dated today or later counts: the employee cannot see draft
 * shifts, and past shifts need no notice. An assignment is "informed" once a
 * Planning email that lists it was sent (features/planning-notifications/).
 */
class UninformedPlanning
{
    /**
     * Every upcoming published assignment of one employee, informed or not,
     * oldest first. This is what a Planning email lists.
     *
     * @return Collection<int, ShiftAssignment>
     */
    public function upcomingFor(Employee $employee): Collection
    {
        return $this->published($this->upcoming()->where('employee_id', $employee->id));
    }

    /**
     * The employees with uninformed planning, ordered by name.
     *
     * With `$excludeQueued`, shifts already listed in a queued (outbox) Planning
     * email are left out. They are still uninformed until the email is sent, but
     * sending again would email the employee twice.
     *
     * With `$reachableOnly`, employees without an email address are left out.
     * Nobody can email them, so the Planning email skips them. The report keeps
     * them and marks them with `has_email`.
     *
     * @return Collection<int, array{id: int, name: string, has_email: bool, business_line: ?string, uninformed_count: int, first_date: string}>
     */
    public function summary(?int $businessLineId = null, bool $excludeQueued = false, bool $reachableOnly = false): Collection
    {
        $query = $this->upcoming()
            ->whereNull('informed_at')
            ->when($excludeQueued, fn ($q) => $q->whereNotIn('id', $this->queuedAssignmentIds()))
            ->when($reachableOnly, fn ($q) => $q->whereHas(
                'employee',
                fn ($employee) => $employee->whereNotNull('email'),
            ))
            ->when($businessLineId !== null, fn ($q) => $q->whereHas(
                'employee',
                fn ($employee) => $employee->where('business_line_id', $businessLineId),
            ));

        return $this->published($query)
            ->groupBy('employee_id')
            ->map(function (Collection $assignments) {
                /** @var Employee $employee */
                $employee = $assignments->first()->employee;

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'has_email' => $employee->email !== null,
                    'business_line' => $employee->businessLine?->abbreviation,
                    'uninformed_count' => $assignments->count(),
                    'first_date' => $assignments->first()->date->toDateString(),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();
    }

    /** @return array<int, int> */
    private function queuedAssignmentIds(): array
    {
        return Message::query()
            ->where('type', MessageType::Planning->value)
            ->where('status', 'outbox')
            ->whereNotNull('assignment_ids')
            ->pluck('assignment_ids')
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    private function upcoming()
    {
        return ShiftAssignment::query()
            ->whereDate('date', '>=', Carbon::today()->toDateString())
            ->orderBy('date')
            ->orderBy('id');
    }

    /**
     * Keeps the assignments whose workcenter is published for their week.
     *
     * @return Collection<int, ShiftAssignment>
     */
    private function published($query): Collection
    {
        $assignments = $query->with(['employee.businessLine', 'workcenter', 'shift'])->get();

        if ($assignments->isEmpty()) {
            return $assignments;
        }

        $weekKey = fn (ShiftAssignment $a) => $a->date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $publishedPairs = PublishedWeek::query()
            ->whereIn('week_start', $assignments->map($weekKey)->unique()->values())
            ->whereIn('workcenter_id', $assignments->pluck('workcenter_id')->unique()->values())
            ->get(['week_start', 'workcenter_id'])
            ->map(fn (PublishedWeek $p) => "{$p->week_start->toDateString()}:{$p->workcenter_id}")
            ->flip();

        return $assignments
            ->filter(fn (ShiftAssignment $a) => $publishedPairs->has("{$weekKey($a)}:{$a->workcenter_id}"))
            ->values();
    }
}
