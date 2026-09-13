<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkcenterShiftDateOverride extends Model
{
    protected $fillable = [
        'workcenter_id',
        'shift_id',
        'date',
        'spots',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spots' => 'integer',
        ];
    }
}
