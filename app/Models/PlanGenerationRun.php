<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanGenerationRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /** A run in one of these statuses blocks a new Generate click for the same cycle. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING];

    protected $fillable = [
        'cycle_start',
        'status',
        'changes',
        'unfulfilled',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'cycle_start' => 'date:Y-m-d',
            'changes' => 'array',
            'unfulfilled' => 'array',
        ];
    }
}
