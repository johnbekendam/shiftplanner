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
