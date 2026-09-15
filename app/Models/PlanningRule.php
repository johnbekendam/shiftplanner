<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningRule extends Model
{
    /** Types with at most one row — a second is rejected. */
    public const SINGLETON_TYPES = [
        'max_hours_per_week',
        'max_shifts_per_day',
        'not_preferred_shift',
    ];

    /** Types that may have many rows, one per scope. */
    public const SCOPED_TYPES = [
        'competence_required',
        'business_line_preference',
    ];

    public const TYPES = [
        ...self::SINGLETON_TYPES,
        ...self::SCOPED_TYPES,
    ];

    protected $fillable = [
        'type',
        'mode',
        'severity',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'severity' => 'integer',
            'config' => 'array',
        ];
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'mode' => $this->mode,
            'severity' => $this->severity,
            'config' => $this->config ?? [],
        ];
    }
}
