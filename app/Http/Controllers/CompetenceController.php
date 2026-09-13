<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompetenceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueName()],
        ]);

        Competence::create([
            'name' => $data['name'],
            'position' => (int) Competence::max('position') + 1,
        ]);

        return back()->with('success', __('competences.flash.added'));
    }

    public function update(Request $request, Competence $competence)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueName($competence)],
        ]);

        $competence->update(['name' => $data['name']]);

        return back()->with('success', __('competences.flash.renamed'));
    }

    public function destroy(Competence $competence)
    {
        $competence->delete();

        return back()->with('success', __('competences.flash.deleted'));
    }

    public function move(Request $request, Competence $competence)
    {
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $neighbour = Competence::query()
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('position', '<', $competence->position)->reorder('position', 'desc'),
                fn ($q) => $q->where('position', '>', $competence->position)->reorder('position', 'asc'),
            )
            ->first();

        if ($neighbour) {
            $mine = $competence->position;
            $competence->update(['position' => $neighbour->position]);
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
            Competence::whereKey($id)->update(['position' => $index]);
        }

        return back();
    }

    /** Rejects anything but the full, current set of competence ids. */
    private function completeIdSet(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $current = Competence::query()->pluck('id')->sort()->values()->all();
            $given = collect($value)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $given) {
                $fail(__('competences.error.reorder_mismatch'));
            }
        };
    }

    /** Case-insensitive uniqueness on the competence name, ignoring one row. */
    private function uniqueName(?Competence $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = Competence::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail(__('competences.error.name_taken'));
            }
        };
    }
}
