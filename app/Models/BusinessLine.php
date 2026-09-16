<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'abbreviation',
        'description',
        'target_fte',
        'position',
        'responsible_user_id',
    ];

    protected function casts(): array
    {
        return [
            'target_fte' => 'float',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($query) => $query->orderBy('position'));
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'abbreviation' => $this->abbreviation,
            'description' => $this->description,
            'target_fte' => $this->target_fte,
            'position' => $this->position,
            'responsible_user_id' => $this->responsible_user_id,
        ];
    }
}
