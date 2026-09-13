<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workcenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'position',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', fn ($query) => $query
            ->orderByRaw('archived_at is not null')
            ->orderBy('position'));
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'workcenter_shift');
    }

    /** The shape shared with the front end. */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'position' => $this->position,
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
