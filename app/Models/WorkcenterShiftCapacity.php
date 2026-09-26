<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkcenterShiftCapacity extends Model
{
    protected $fillable = [
        'workcenter_id',
        'shift_id',
        'weekday',
        'spots',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'spots' => 'integer',
        ];
    }

    public function workcenter(): BelongsTo
    {
        return $this->belongsTo(Workcenter::class);
    }
}
