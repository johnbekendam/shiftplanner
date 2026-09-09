<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
    ];

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

    public function recurringAvailabilities(): HasMany
    {
        return $this->hasMany(RecurringAvailability::class);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ];
    }
}
