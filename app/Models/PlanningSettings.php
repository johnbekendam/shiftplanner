<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningSettings extends Model
{
    /** One row only. */
    public const ID = 1;

    protected $fillable = [
        'fte_hours',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'fte_hours' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
        ];
    }

    /** The single settings row, created with defaults on first read. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => self::ID], ['fte_hours' => 40]);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'fte_hours' => $this->fte_hours,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
        ];
    }
}
