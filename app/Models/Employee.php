<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'first_name',
        'last_name',
        'email',
        'weekly_hours',
        'business_line_id',
    ];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'integer',
        ];
    }

    /** The full name, "First Last". Read-only; edit first_name/last_name. */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    public function personalLink(): HasOne
    {
        return $this->hasOne(EmployeePersonalLink::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
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

    /** Availability questions the employee answered with yes. */
    public function availabilityQuestions(): BelongsToMany
    {
        return $this->belongsToMany(AvailabilityQuestion::class);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
