<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One date-specific change to an employee's weekly availability. A row
 * with a shift sets that shift's level on the date. A row without a
 * shift blocks the whole day.
 */
class AvailabilityOverride extends Model
{
    /** Levels a shift override can store. A whole-day block always stores "unavailable". */
    public const LEVELS = ['available', 'not_preferred', 'unavailable'];

    protected $fillable = [
        'employee_id',
        'date',
        'shift_id',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'shift_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'shift_id' => $this->shift_id,
            'level' => $this->level,
        ];
    }
}
