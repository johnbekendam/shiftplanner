<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workcenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'position',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($query) => $query
            ->orderByRaw('archived_at is not null')
            ->orderBy('position'));
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'workcenter_shift');
    }

    /** The open-spot count for one shift on one date: an override if one exists, the weekday default otherwise. */
    public function spotsFor(Shift $shift, Carbon $date): int
    {
        $override = WorkcenterShiftDateOverride::query()
            ->where('workcenter_id', $this->id)
            ->where('shift_id', $shift->id)
            ->whereDate('date', $date)
            ->first();

        if ($override) {
            return $override->spots;
        }

        return WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $this->id)
            ->where('shift_id', $shift->id)
            ->where('weekday', $date->isoWeekday())
            ->value('spots') ?? 0;
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
