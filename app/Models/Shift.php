<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    /** Block size, in hours, that {@see capHoursBetween()} rounds to. */
    public const CAP_HOURS_STEP = 4;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'visible_by_default',
    ];

    protected function casts(): array
    {
        return [
            'visible_by_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($query) => $query->orderBy('start_time')->orderBy('name'));
    }

    /** Store and expose a wall-clock time as `H:i`, whatever the driver returns. */
    protected function startTime(): Attribute
    {
        return $this->clockTime();
    }

    protected function endTime(): Attribute
    {
        return $this->clockTime();
    }

    private function clockTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : substr($value, 0, 5),
            set: fn ($value) => $value === null ? null : substr($value, 0, 5),
        );
    }

    /** Hours between start and end time, wrapping past midnight for an overnight shift. */
    public function durationHours(): float
    {
        return self::durationHoursBetween($this->start_time, $this->end_time);
    }

    /** Same calculation as {@see durationHours()}, for callers holding raw `H:i` strings instead of a model. */
    public static function durationHoursBetween(string $startTime, string $endTime): float
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));
        $minutes = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);

        if ($minutes <= 0) {
            $minutes += 24 * 60;
        }

        return $minutes / 60;
    }

    /** Hours this shift counts toward the max-hours cap: {@see capHoursBetween()}. */
    public function capHours(): float
    {
        return self::capHoursBetween($this->start_time, $this->end_time);
    }

    /**
     * The duration rounded to the nearest CAP_HOURS_STEP (ties up), never below
     * one step. Only the max-hours cap uses it, so a slightly long shift (breaks
     * included) still fits a contract counted in whole blocks.
     */
    public static function capHoursBetween(string $startTime, string $endTime): float
    {
        $steps = max(1, round(self::durationHoursBetween($startTime, $endTime) / self::CAP_HOURS_STEP));

        return (float) ($steps * self::CAP_HOURS_STEP);
    }

    public function recurringAvailabilities(): HasMany
    {
        return $this->hasMany(RecurringAvailability::class);
    }

    public function workcenters(): BelongsToMany
    {
        return $this->belongsToMany(Workcenter::class, 'workcenter_shift');
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'visible_by_default' => $this->visible_by_default,
        ];
    }
}
