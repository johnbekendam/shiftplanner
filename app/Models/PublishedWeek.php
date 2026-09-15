<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishedWeek extends Model
{
    protected $fillable = [
        'week_start',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date:Y-m-d',
        ];
    }
}
