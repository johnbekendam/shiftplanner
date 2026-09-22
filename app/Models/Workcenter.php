<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Workcenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'responsible',
        'live_token',
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

        static::creating(function (self $workcenter) {
            $workcenter->live_token ??= Str::random(40);
        });
    }

    /** Replaces the live-screen token. The old URL stops working at once. */
    public function regenerateLiveToken(): void
    {
        $this->update(['live_token' => Str::random(40)]);
    }

    public function liveUrl(): string
    {
        return url("/live/{$this->live_token}");
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'workcenter_shift');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)->withPivot('mode');
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
            'responsible' => $this->responsible,
            'position' => $this->position,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
