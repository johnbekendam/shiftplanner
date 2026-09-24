<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\User;

class EmployeeAuditLogger
{
    public const EMPLOYEE_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'weekly_hours',
        'weekly_hours_minimum',
        'business_line_id',
        'confirmed',
    ];

    public function record(
        Employee $employee,
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $oldValues,
        array $newValues,
        string $source,
        ?User $actor = null,
        ?string $actorType = null,
    ): EmployeeAuditEvent {
        return EmployeeAuditEvent::create([
            'employee_id' => $employee->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'source' => $source,
            'actor_type' => $actorType ?? ($actor ? 'user' : null),
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'actor_role' => $actor?->role,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}