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
        array $actorSnapshot = [],
    ): EmployeeAuditEvent {
        return $this->recordForEmployeeId(
            $employee->id,
            $action,
            $subjectType,
            $subjectId,
            $oldValues,
            $newValues,
            $source,
            $actor,
            $actorType,
            $actorSnapshot,
        );
    }

    public function recordForEmployeeId(
        int $employeeId,
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $oldValues,
        array $newValues,
        string $source,
        ?User $actor = null,
        ?string $actorType = null,
        array $actorSnapshot = [],
    ): EmployeeAuditEvent {
        return EmployeeAuditEvent::create([
            'employee_id' => $employeeId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'source' => $source,
            'actor_type' => $actorType ?? ($actor ? 'user' : ($actorSnapshot['type'] ?? null)),
            'actor_id' => $actor?->id ?? ($actorSnapshot['id'] ?? null),
            'actor_name' => $actor?->name ?? ($actorSnapshot['name'] ?? null),
            'actor_email' => $actor?->email ?? ($actorSnapshot['email'] ?? null),
            'actor_role' => $actor?->role ?? ($actorSnapshot['role'] ?? null),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function recordPersonal(
        Employee $employee,
        string $action,
        string $subjectType,
        ?int $subjectId,
        array $oldValues,
        array $newValues,
    ): EmployeeAuditEvent {
        return $this->record(
            $employee,
            $action,
            $subjectType,
            $subjectId,
            $oldValues,
            $newValues,
            'employee_personal_link',
            actorType: 'employee',
            actorSnapshot: ['id' => $employee->id, 'name' => $employee->name, 'email' => $employee->email],
        );
    }
}
