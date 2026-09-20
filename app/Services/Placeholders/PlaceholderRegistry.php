<?php

namespace App\Services\Placeholders;

use App\Models\Employee;
use App\Models\User;
use App\Models\ShiftAssignment;
use App\Services\EmployeePersonalLinkService;
use App\Services\UninformedPlanning;

/**
 * The placeholder tokens a Compose subject/body may use, usable in every
 * composable MessageType (see App\Enums\MessageType::composable()) rather
 * than a fixed list per type. Each definition resolves against either
 * recipient source; a resolver returning null means the token cannot be
 * filled for that recipient — see App\Services\MessagePlaceholders.
 */
class PlaceholderRegistry
{
    public function __construct(
        private EmployeePersonalLinkService $links,
        private UninformedPlanning $planning,
    ) {}

    /**
     * @return array<int, array{token: string, resolveEmployee: callable(Employee): ?string, resolveUser: callable(User): ?string, sample: string}>
     */
    public function definitions(): array
    {
        return [
            [
                'token' => ':name',
                'resolveEmployee' => fn (Employee $employee) => $employee->first_name,
                'resolveUser' => fn (User $user) => $user->name,
                'sample' => __('mailbox.preview.sample_name'),
            ],
            [
                'token' => ':link',
                'resolveEmployee' => fn (Employee $employee) => $this->links->linkFor($employee),
                'resolveUser' => fn (User $user) => $user->employee ? $this->links->linkFor($user->employee) : null,
                'sample' => url('/personal/EXAMPLE-TOKEN'),
            ],
            [
                'token' => ':planning',
                'resolveEmployee' => fn (Employee $employee) => $this->planningList($employee),
                'resolveUser' => fn (User $user) => $user->employee ? $this->planningList($user->employee) : null,
                'sample' => __('mailbox.preview.sample_planning'),
            ],
        ];
    }

    /**
     * A Markdown list of the employee's upcoming published shifts, or null
     * when there are none, so the recipient is reported as unresolved.
     */
    private function planningList(Employee $employee): ?string
    {
        $assignments = $this->planning->upcomingFor($employee);

        if ($assignments->isEmpty()) {
            return null;
        }

        return $assignments
            ->map(fn (ShiftAssignment $a) => sprintf(
                '- %s %s – %s %s–%s – %s',
                $a->date->format('l'),
                $a->date->format('d-m-Y'),
                $a->shift->name,
                substr($a->shift->start_time, 0, 5),
                substr($a->shift->end_time, 0, 5),
                $a->workcenter->name,
            ))
            ->implode("\n");
    }
}
