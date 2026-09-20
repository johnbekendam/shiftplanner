<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'workcenter_id',
        'shift_id',
        'date',
        'fixed',
        'informed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'fixed' => 'boolean',
            'informed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workcenter(): BelongsTo
    {
        return $this->belongsTo(Workcenter::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee->name,
            'fixed' => $this->fixed,
        ];
    }
}
