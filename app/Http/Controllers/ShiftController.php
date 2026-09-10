<?php

namespace App\Http\Controllers;

use App\Models\PlanningSettings;
use App\Models\Shift;
use Closure;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function store(Request $request)
    {
        Shift::create($this->validated($request));

        return back()->with('success', __('shifts.flash.added'));
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($this->validated($request, $shift));

        return back()->with('success', __('shifts.flash.updated'));
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return back()->with('success', __('shifts.flash.deleted'));
    }

    public function updateNote(Request $request)
    {
        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:20000'],
        ])['note'] ?? '';

        PlanningSettings::current()->update([
            'shift_note' => trim($note) === '' ? null : $note,
        ]);

        return back()->with('success', __('shifts.flash.note_saved'));
    }

    public function updateScheduleNote(Request $request)
    {
        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:20000'],
        ])['note'] ?? '';

        PlanningSettings::current()->update([
            'shift_schedule_note' => trim($note) === '' ? null : $note,
        ]);

        return back()->with('success', __('shifts.flash.schedule_note_saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Shift $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', $this->uniqueName($ignore)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'end_time.after' => __('shifts.error.time_range'),
        ]);
    }

    /** Case-insensitive uniqueness on the name, ignoring one row. */
    private function uniqueName(?Shift $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = Shift::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail(__('shifts.error.name_taken'));
            }
        };
    }
}
