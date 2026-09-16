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
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('business_line_id', $ignore?->id ?? 0)
                    ->where('is_active', true),
            ],
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
