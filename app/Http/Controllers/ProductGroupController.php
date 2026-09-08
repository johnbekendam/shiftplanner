<?php

namespace App\Http\Controllers;

use App\Models\ProductGroup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductGroupController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueName()],
        ]);

        ProductGroup::create([
            'name' => $data['name'],
            'position' => (int) ProductGroup::max('position') + 1,
        ]);

        return back()->with('success', __('product_groups.flash.added'));
    }

    public function update(Request $request, ProductGroup $productGroup)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueName($productGroup)],
        ]);

        $productGroup->update(['name' => $data['name']]);

        return back()->with('success', __('product_groups.flash.renamed'));
    }

    public function destroy(ProductGroup $productGroup)
    {
        $productGroup->delete();

        return back()->with('success', __('product_groups.flash.deleted'));
    }

    public function move(Request $request, ProductGroup $productGroup)
    {
        $direction = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ])['direction'];

        $neighbour = ProductGroup::query()
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('position', '<', $productGroup->position)->reorder('position', 'desc'),
                fn ($q) => $q->where('position', '>', $productGroup->position)->reorder('position', 'asc'),
            )
            ->first();

        if ($neighbour) {
            $mine = $productGroup->position;
            $productGroup->update(['position' => $neighbour->position]);
            $neighbour->update(['position' => $mine]);
        }

        return back();
    }

    /** Case-insensitive uniqueness on the product group name, ignoring one row. */
    private function uniqueName(?ProductGroup $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
            $exists = ProductGroup::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();

            if ($exists) {
                $fail(__('product_groups.error.name_taken'));
            }
        };
    }
}
