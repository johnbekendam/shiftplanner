<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use App\Services\WhatsNew;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    /** The lowest real weekly-hours choice. Below it an employee picks 0. */
    public const MIN_WEEKLY_HOURS = 20;

    /** The default value for a newly created employee. */
    public const DEFAULT_WEEKLY_HOURS = 0;

    /**
     * Allowed values for the weekly_hours column: 20 to 48 in steps of 4,
     * plus 0 for an employee who cannot work the minimum (no available hours).
     */
    public const WEEKLY_HOURS_OPTIONS = [0, 20, 24, 28, 32, 36, 40, 44, 48];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'weekly_hours',
        'weekly_hours_minimum',
        'business_line_id',
        'confirmed',
        'archived_at',
        'available_from',
        'whats_new_seen_at',
    ];

    /** A new person starts at the newest What's new entry: older changes are not news to them. */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->whats_new_seen_at ??= app(WhatsNew::class)->latestDate();
        });
    }

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'integer',
            'weekly_hours_minimum' => 'integer',
            'confirmed' => 'boolean',
            'archived_at' => 'datetime',
            'available_from' => 'date:Y-m-d',
            'whats_new_seen_at' => 'date:Y-m-d',
        ];
    }

    /** The full name, "First Last". Read-only; edit first_name/last_name. */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    public function personalLink(): HasOne
    {
        return $this->hasOne(EmployeePersonalLink::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(EmployeeHoliday::class)->orderBy('start_date');
    }

    /** Date-specific availability, oldest date first. */
    public function availabilityOverrides(): HasMany
    {
        return $this->hasMany(AvailabilityOverride::class)->orderBy('date')->orderBy('shift_id');
    }

    public function recurringAvailabilities(): HasMany
    {
        return $this->hasMany(RecurringAvailability::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function effectiveWeeklyHoursMinimum(): int
    {
        return $this->weekly_hours_minimum ?? PlanningSettings::current()->weekly_hours_minimum;
    }

    public function competences(): BelongsToMany
    {
        return $this->belongsToMany(Competence::class);
    }

    public function workcenters(): BelongsToMany
    {
        return $this->belongsToMany(Workcenter::class);
    }

    /**
     * The shifts this employee can see and set availability for.
     *
     * Every shift visible by default, plus the shifts the employee's
     * workcenters run, regardless of visible_by_default.
     */
    public function effectiveShifts(): Collection
    {
        $workcenterIds = $this->workcenters()->pluck('workcenters.id');

        return Shift::query()
            ->where('visible_by_default', true)
            ->orWhereHas('workcenters', fn ($q) => $q->whereIn('workcenters.id', $workcenterIds))
            ->get();
    }

    /**
     * The ISO weekdays (1-7) each effective shift runs on for this
     * employee: a day where one of its capacity workcenters has spots for
     * it. See {@see capacityWorkcenterIds()}.
     *
     * @return array<int, int[]> shift_id => sorted weekdays
     */
    public function shiftWeekdays(): array
    {
        $workcenterIds = $this->capacityWorkcenterIds();
        $shiftIds = collect(array_keys($workcenterIds));

        $weekdays = WorkcenterShiftCapacity::query()
            ->whereIn('shift_id', $shiftIds)
            ->where('spots', '>', 0)
            ->get(['workcenter_id', 'shift_id', 'weekday'])
            ->filter(fn ($c) => $workcenterIds[$c->shift_id]->contains($c->workcenter_id))
            ->groupBy('shift_id');

        return $shiftIds->mapWithKeys(fn (int $id) => [
            $id => $weekdays->get($id, collect())->pluck('weekday')->map(fn ($d) => (int) $d)->unique()->sort()->values()->all(),
        ])->all();
    }

    /**
     * Dates where a workcenter date capacity override changes whether an
     * effective shift runs, compared to its weekdays. On a date, a shift
     * runs when one of its capacity workcenters has spots for it: its date
     * override when it has one, else its weekly capacity. Only shifts with
     * such a date are listed.
     *
     * @return array<int, array{open: string[], closed: string[]}> shift_id => sorted Y-m-d dates
     */
    public function shiftDateExceptions(): array
    {
        $workcenterIds = $this->capacityWorkcenterIds();
        $shiftIds = collect(array_keys($workcenterIds));
        $weekdays = $this->shiftWeekdays();

        $overrides = WorkcenterShiftDateOverride::query()
            ->whereIn('shift_id', $shiftIds)
            ->get()
            ->filter(fn ($o) => $workcenterIds[$o->shift_id]->contains($o->workcenter_id));
        if ($overrides->isEmpty()) {
            return [];
        }

        $capacities = WorkcenterShiftCapacity::query()
            ->whereIn('shift_id', $shiftIds)
            ->get()
            ->mapWithKeys(fn ($c) => ["{$c->workcenter_id}:{$c->shift_id}:{$c->weekday}" => $c->spots]);

        $exceptions = [];
        foreach ($overrides->groupBy(fn ($o) => "{$o->shift_id}|{$o->date->toDateString()}") as $group) {
            $shiftId = $group->first()->shift_id;
            $date = $group->first()->date;
            $byWorkcenter = $group->keyBy('workcenter_id');

            $runs = $workcenterIds[$shiftId]->contains(fn ($workcenterId) => ($byWorkcenter->get($workcenterId)?->spots
                ?? $capacities->get("{$workcenterId}:{$shiftId}:{$date->isoWeekday()}", 0)) > 0);
            $runsWeekly = in_array($date->isoWeekday(), $weekdays[$shiftId] ?? [], true);

            if ($runs !== $runsWeekly) {
                $exceptions[$shiftId][$runs ? 'open' : 'closed'][] = $date->toDateString();
            }
        }

        return collect($exceptions)->map(fn (array $dates) => [
            'open' => collect($dates['open'] ?? [])->sort()->values()->all(),
            'closed' => collect($dates['closed'] ?? [])->sort()->values()->all(),
        ])->all();
    }

    /**
     * The given shifts that run on the date: {@see shiftWeekdays()} plus
     * {@see shiftDateExceptions()}.
     *
     * @param  int[]  $shiftIds
     * @return int[]
     */
    public function shiftsRunningOn(string $date, array $shiftIds): array
    {
        $weekday = Carbon::parse($date)->isoWeekday();
        $weekdays = $this->shiftWeekdays();
        $exceptions = $this->shiftDateExceptions();

        return array_values(array_filter($shiftIds, function (int $id) use ($date, $weekday, $weekdays, $exceptions) {
            if (in_array($date, $exceptions[$id]['closed'] ?? [], true)) {
                return false;
            }

            return in_array($date, $exceptions[$id]['open'] ?? [], true)
                || in_array($weekday, $weekdays[$id] ?? [], true);
        }));
    }

    /**
     * Per effective shift, the workcenters whose capacity decides when it
     * runs: the employee's own when one of them runs the shift, else every
     * active one.
     *
     * @return array<int, \Illuminate\Support\Collection<int, int>> shift_id => workcenter ids
     */
    private function capacityWorkcenterIds(): array
    {
        $ownIds = $this->workcenters()->pluck('workcenters.id');
        $ownShiftIds = Shift::whereHas('workcenters', fn ($q) => $q->whereIn('workcenters.id', $ownIds))->pluck('id');
        $activeIds = Workcenter::query()->whereNull('archived_at')->pluck('id');

        return $this->effectiveShifts()->pluck('id')->mapWithKeys(fn (int $id) => [
            $id => $ownShiftIds->contains($id) ? $ownIds : $activeIds,
        ])->all();
    }

    /** The availability pages' shift list: each shift's payload plus the weekdays and single dates it runs on. */
    public function availabilityShiftsPayload(Collection $shifts): array
    {
        $weekdays = $this->shiftWeekdays();
        $exceptions = $this->shiftDateExceptions();

        return $shifts
            ->map(fn (Shift $shift) => [
                ...$shift->toPayload(),
                'weekdays' => $weekdays[$shift->id] ?? [],
                'open_dates' => $exceptions[$shift->id]['open'] ?? [],
                'closed_dates' => $exceptions[$shift->id]['closed'] ?? [],
            ])
            ->values()
            ->all();
    }

    /** Availability questions the employee answered with yes. */
    public function availabilityQuestions(): BelongsToMany
    {
        return $this->belongsToMany(AvailabilityQuestion::class);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function scopeActive($query)
    {
        return $query->whereNull('employees.archived_at');
    }
}
