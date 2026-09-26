<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AvailabilityOverride;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait SetsDateAvailability
{
    /**
     * Replace every override of one date with the requested whole-day
     * block and shift levels. An omitted shift follows the weekly default.
     *
     * @return array{0: array, 1: array} the date's state before and after
     */
    protected function setDate(Request $request, Employee $employee, string $date): array
    {
        $data = $request->validate([
            'blocked' => ['required', 'boolean'],
            'shifts' => ['present', 'array'],
            'shifts.*' => ['required', Rule::in(AvailabilityOverride::LEVELS)],
        ]);

        $shiftIds = array_map('intval', array_keys($data['shifts']));
        $effectiveIds = $employee->effectiveShifts()->pluck('id')->all();
        if (array_diff($shiftIds, $effectiveIds) !== []) {
            throw ValidationException::withMessages(['shifts' => __('availability.error.shift_hidden')]);
        }

        $before = $this->dateState($employee, $date);

        DB::transaction(function () use ($employee, $date, $data) {
            $employee->availabilityOverrides()->whereDate('date', $date)->delete();

            if ($data['blocked']) {
                $employee->availabilityOverrides()->create(['date' => $date, 'shift_id' => null, 'level' => 'unavailable']);
            }

            foreach ($data['shifts'] as $shiftId => $level) {
                $employee->availabilityOverrides()->create(['date' => $date, 'shift_id' => (int) $shiftId, 'level' => $level]);
            }
        });

        return [$before, $this->dateState($employee, $date)];
    }

    /** The audit shape of one date: its block flag and shift levels. */
    private function dateState(Employee $employee, string $date): array
    {
        $rows = $employee->availabilityOverrides()->whereDate('date', $date)->get();

        return [
            'date' => $date,
            'blocked' => $rows->contains(fn ($row) => $row->shift_id === null),
            'shifts' => $rows->whereNotNull('shift_id')->mapWithKeys(fn ($row) => [$row->shift_id => $row->level])->all(),
        ];
    }
}
