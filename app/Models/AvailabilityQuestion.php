<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AvailabilityQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($query) => $query->orderBy('position'));
    }

    /** Employees who answered this question with yes. */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
        ];
    }
}
