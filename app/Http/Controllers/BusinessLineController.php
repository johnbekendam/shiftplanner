<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessLineController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);

        BusinessLine::create([
            ...$data,
            'position' => (int) BusinessLine::max('position') + 1,
        ]);

        return back()->with('success', __('business_lines.flash.added'));
    }

    public function update(Request $request, BusinessLine $businessLine)
    {
        $businessLine->update($this->validated($request, $businessLine));

        return back()->with('success', __('business_lines.flash.updated'));
    }

    public function destroy(BusinessLine $businessLine)
    {
        $businessLine->delete();

        return back()->with('success', __('business_lines.flash.deleted'));
    }

    public function move(Request $request, BusinessLine $businessLine)
    {
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $neighbour = BusinessLine::query()
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('position', '<', $businessLine->position)->reorder('position', 'desc'),
                fn ($q) => $q->where('position', '>', $businessLine->position)->reorder('position', 'asc'),
            )
            ->first();

        if ($neighbour) {
            $mine = $businessLine->position;
            $businessLine->update(['position' => $neighbour->position]);
            $neighbour->update(['position' => $mine]);
        }

        return back();
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
            BusinessLine::whereKey($id)->update(['position' => $index]);
        }

        return back();
    }

    /** Rejects anything but the full, current set of business-line ids. */
    private function completeIdSet(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $current = BusinessLine::query()->pluck('id')->sort()->values()->all();
            $given = collect($value)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $given) {
                $fail(__('business_lines.error.reorder_mismatch'));
            }
        };
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?BusinessLine $ignore = null): array
    {
        return $request->validate([
            'abbreviation' => ['required', 'string', 'max:10', $this->uniqueAbbreviation($ignore)],
            'description' => ['required', 'string', 'max:255'],
            'target_fte' => ['required', 'numeric', 'min:0'],
        ]);
    }

    /** Case-insensitive uniqueness on the abbreviation, ignoring one row. */
    private function uniqueAbbreviation(?BusinessLine $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = BusinessLine::query()
                ->whereRaw('lower(abbreviation) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail(__('business_lines.error.abbreviation_taken'));
            }
        };
    }
}
