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
            AvailabilityQuestion::whereKey($id)->update(['position' => $index]);
        }

        return back();
    }

    /** Rejects anything but the full, current set of question ids. */
    private function completeIdSet(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $current = AvailabilityQuestion::query()->pluck('id')->sort()->values()->all();
            $given = collect($value)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $given) {
                $fail(__('questions.error.reorder_mismatch'));
            }
        };
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
