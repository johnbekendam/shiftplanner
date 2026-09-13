<?php

namespace App\Http\Controllers;

use App\Models\Workcenter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkcenterController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);

        Workcenter::create([
            'name' => $data['name'],
            'position' => (int) Workcenter::max('position') + 1,
            'archived_at' => $data['archived'] ? now() : null,
        ]);

        return back()->with('success', __('workcenters.flash.added'));
    }

    public function update(Request $request, Workcenter $workcenter)
    {
        $data = $this->validated($request, $workcenter);

        $workcenter->update([
            'name' => $data['name'],
            'archived_at' => $data['archived'] ? ($workcenter->archived_at ?? now()) : null,
        ]);

        return back()->with('success', __('workcenters.flash.updated'));
    }

    public function destroy(Workcenter $workcenter)
    {
        if ($workcenter->shifts()->exists()) {
            throw ValidationException::withMessages(['workcenter' => __('workcenters.error.has_shifts')]);
        }

        $workcenter->delete();

        return back()->with('success', __('workcenters.flash.deleted'));
    }

    /**
     * One request replaces a sequence of one-step moves: the client sends
     * the full desired order, and every row's position is set in one pass.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', $this->completeIdSet()],
            'ids.*' => ['integer', 'distinct'],
        ]);

        foreach ($request->input('ids') as $index => $id) {
            Workcenter::whereKey($id)->update(['position' => $index]);
        }

        return back();
    }

    /** Rejects anything but the full, current set of workcenter ids. */
    private function completeIdSet(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $current = Workcenter::query()->pluck('id')->sort()->values()->all();
            $given = collect($value)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $given) {
                $fail(__('workcenters.error.reorder_mismatch'));
            }
        };
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Workcenter $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', $this->uniqueName($ignore)],
            'archived' => ['sometimes', 'boolean'],
        ]) + ['archived' => $request->boolean('archived')];
    }

    /** Case-insensitive uniqueness on the name, ignoring one row. */
    private function uniqueName(?Workcenter $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = Workcenter::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail(__('workcenters.error.name_taken'));
            }
        };
    }
}
