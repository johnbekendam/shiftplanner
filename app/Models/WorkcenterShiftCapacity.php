<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
