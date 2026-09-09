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
