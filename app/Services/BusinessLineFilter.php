<?php

namespace App\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The `business_lines[]` query filter shared by the Employees page and the
 * Roster page. Values are business line ids and `none` (no business line).
 * No query means every business line; a query with no valid value (the
 * front end sends `__empty__`) means none at all.
 */
class BusinessLineFilter
{
    /** @param  array<int>  $ids */
    private function __construct(
        private readonly bool $active,
        private readonly array $ids,
        private readonly bool $includesNone,
        private readonly Collection $businessLines,
    ) {}

    public static function fromRequest(Request $request, Collection $businessLines): self
    {
        $requested = $request->query('business_lines');

        if (! is_array($requested)) {
            return new self(false, [], false, $businessLines);
        }

        $valid = array_values(array_intersect($requested, [...$businessLines->pluck('id')->map(strval(...)), 'none']));

        return new self(
            true,
            array_map('intval', array_values(array_filter($valid, fn ($id) => $id !== 'none'))),
            in_array('none', $valid, true),
            $businessLines,
        );
    }

    /** Limits $query to the selected business lines on $column. No-op without a filter. */
    public function apply(Builder $query, string $column): void
    {
        if (! $this->active) {
            return;
        }

        $query->where(function ($q) use ($column) {
            if ($this->ids !== []) {
                $q->orWhereIn($column, $this->ids);
            }
            if ($this->includesNone) {
                $q->orWhereNull($column);
            }
            if ($this->ids === [] && ! $this->includesNone) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /** The selected values for the page: ids, then `none`. Every value without a filter. */
    public function selected(): array
    {
        return $this->active
            ? [...$this->ids, ...($this->includesNone ? ['none'] : [])]
            : [...$this->businessLines->pluck('id')->all(), 'none'];
    }
}
