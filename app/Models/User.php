<?php

namespace App\Models;

use App\Services\WhatsNew;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_MANAGER];

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'employee_id',
        'business_line_id',
        'planning_filter',
        'whats_new_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** A new user starts at the newest What's new entry: older changes are not news to them. */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->whats_new_seen_at ??= app(WhatsNew::class)->latestDate();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
            'planning_filter' => 'array',
            'whats_new_seen_at' => 'date:Y-m-d',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    public function loginLinks(): HasMany
    {
        return $this->hasMany(LoginLink::class);
    }
}
