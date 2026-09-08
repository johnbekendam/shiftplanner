<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringAvailability extends Model
{
    use HasFactory;

    /** The three parts of a day a cell can cover. */
    public const DAYPARTS = ['morning', 'afternoon', 'evening'];

    /** Stored levels. 'available' is the absence of a row, so it is not here. */
    public const LEVELS = ['not_preferred', 'unavailable'];

    protected $fillable = [
        'employee_id',
        'weekday',
        'daypart',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
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
            'weekday' => $this->weekday,
            'daypart' => $this->daypart,
            'level' => $this->level,
        ];
    }
}
