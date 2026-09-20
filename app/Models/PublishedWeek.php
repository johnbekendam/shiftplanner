<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PublishedWeek extends Model
{
    protected $fillable = [
        'week_start',
        'workcenter_id',
        'planner_open',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date:Y-m-d',
            'planner_open' => 'boolean',
        ];
    }

    public function workcenter(): BelongsTo
    {
        return $this->belongsTo(Workcenter::class);
    }

    /**
     * Every published (week, workcenter) pair with a week_start between
     * $start and $end (inclusive) and a workcenter in $workcenterIds, as a
     * flipped `"{week_start}:{workcenter_id}"` lookup set. The single
     * source of truth for "is this pair published" — shared by
     * HeuristicPlanGenerator's lock rule and PlanClearController's delete
     * scope, so the two can never disagree about what's protected.
     */
    public static function lockedPairs(Carbon $start, Carbon $end, Collection $workcenterIds): Collection
    {
        return static::pairs($start, $end, $workcenterIds);
    }

    /**
     * The published pairs the autoplanner may not touch at all: published and
     * not opened by `planner_open`. The generator leaves their open spots
     * empty (features/autoplanner-published-weeks/).
     */
    public static function frozenPairs(Carbon $start, Carbon $end, Collection $workcenterIds): Collection
    {
        return static::pairs($start, $end, $workcenterIds, plannerOpen: false);
    }

    private static function pairs(Carbon $start, Carbon $end, Collection $workcenterIds, ?bool $plannerOpen = null): Collection
    {
        return static::query()
            ->whereBetween('week_start', [$start->toDateString(), $end->toDateString()])
            ->whereIn('workcenter_id', $workcenterIds)
            ->when($plannerOpen !== null, fn ($q) => $q->where('planner_open', $plannerOpen))
            ->get(['week_start', 'workcenter_id'])
            ->map(fn (self $p) => "{$p->week_start->toDateString()}:{$p->workcenter_id}")
            ->flip();
    }
}
