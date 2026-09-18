<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishedWeek extends Model
{
    protected $fillable = [
        'week_start',
        'workcenter_id',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date:Y-m-d',
        ];
    }

    public function workcenter(): BelongsTo
    {
        return $this->belongsTo(Workcenter::class);
    }
}
