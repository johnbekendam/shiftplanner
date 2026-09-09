<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringAvailability extends Model
{
    use HasFactory;

    /** Stored levels. 'available' is the absence of a row, so it is not here. */
    public const LEVELS = ['not_preferred', 'unavailable'];

    protected $fillable = [
        'employee_id',
        'weekday',
        'shift_id',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'shift_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'weekday' => $this->weekday,
            'shift_id' => $this->shift_id,
            'level' => $this->level,
        ];
    }
}
