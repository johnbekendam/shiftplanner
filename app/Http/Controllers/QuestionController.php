<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityQuestion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueText()],
        ]);

        AvailabilityQuestion::create([
            'text' => $data['name'],
            'position' => (int) AvailabilityQuestion::max('position') + 1,
        ]);

        return back()->with('success', __('questions.flash.added'));
    }

    public function update(Request $request, AvailabilityQuestion $question)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueText($question)],
        ]);

        $question->update(['text' => $data['name']]);

        return back()->with('success', __('questions.flash.renamed'));
    }

    public function destroy(AvailabilityQuestion $question)
    {
        $question->delete();

        return back()->with('success', __('questions.flash.deleted'));
    }

    public function move(Request $request, AvailabilityQuestion $question)
    {
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $neighbour = AvailabilityQuestion::query()
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('position', '<', $question->position)->reorder('position', 'desc'),
                fn ($q) => $q->where('position', '>', $question->position)->reorder('position', 'asc'),
            )
            ->first();

        if ($neighbour) {
            $mine = $question->position;
            $question->update(['position' => $neighbour->position]);
            $neighbour->update(['position' => $mine]);
        }

        return back();
    }

    /** Case-insensitive uniqueness on the question text, ignoring one row. */
    private function uniqueText(?AvailabilityQuestion $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = AvailabilityQuestion::query()
                ->whereRaw('lower(text) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail(__('questions.error.text_taken'));
            }
        };
    }
}
