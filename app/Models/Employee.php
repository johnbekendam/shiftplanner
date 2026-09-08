<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    /** The lowest real weekly-hours choice. Below it an employee picks 0. */
    public const MIN_WEEKLY_HOURS = 20;

    /**
     * Allowed values for the weekly_hours column: 20 to 48 in steps of 4,
     * plus 0 for an employee who cannot work the minimum (no available hours).
     */
    public const WEEKLY_HOURS_OPTIONS = [0, 20, 24, 28, 32, 36, 40, 44, 48];

    protected $fillable = [
        'name',
        'email',
        'weekly_hours',
    ];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'integer',
        ];
    }

    public function personalLink(): HasOne
    {
        return $this->hasOne(EmployeePersonalLink::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(EmployeeHoliday::class)->orderBy('start_date');
    }

    public function recurringAvailabilities(): HasMany
    {
        return $this->hasMany(RecurringAvailability::class);
    }

    public function competences(): BelongsToMany
    {
        return $this->belongsToMany(Competence::class);
    }

    public function productGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
