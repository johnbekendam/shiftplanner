<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_view_the_employee_audit_log(): void
    {
        $this->get('/employee-audit')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))
            ->get('/employee-audit')
            ->assertForbidden();
    }

    public function test_administrator_sees_newest_events_with_actor_and_employee_snapshots(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $older = $this->event($employee, [
            'action' => 'created',
            'actor_name' => null,
            'created_at' => Carbon::parse('2026-09-20 09:00:00'),
        ]);
        $newer = $this->event($employee, [
            'action' => 'updated',
            'actor_name' => 'Admin User',
            'actor_email' => 'admin@example.com',
            'created_at' => Carbon::parse('2026-09-21 10:00:00'),
        ]);

        $this->actingAs($admin)->get('/employee-audit')
            ->assertInertia(fn (Assert $page) => $page
                ->component('EmployeeAudit/Index')
                ->has('events.data', 2)
                ->where('events.data.0.id', $newer->id)
                ->where('events.data.0.employee_name', 'Ada Lovelace')
                ->where('events.data.0.actor_name', 'Admin User')
                ->where('events.data.0.actor_email', 'admin@example.com')
                ->where('events.data.0.old_values', ['weekly_hours' => 24])
                ->where('events.data.0.new_values', ['weekly_hours' => 28])
                ->where('events.data.0.created_at', '2026-09-21T10:00:00+00:00')
                ->where('events.data.1.id', $older->id)
                ->where('filters.employee', '')
                ->where('filters.actor', '')
                ->where('filters.action', null)
                ->where('filters.source', null)
                ->where('actions', ['created', 'updated'])
                ->where('sources', ['user'])
            );
    }

    public function test_audit_log_filters_employee_actor_action_and_source(): void
    {
        $admin = User::factory()->admin()->create();
        $ada = Employee::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com']);
        $grace = Employee::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.com']);
        $adaEvent = $this->event($ada, [
            'action' => 'updated',
            'source' => 'user',
            'actor_name' => 'Alice Admin',
            'actor_email' => 'alice@example.com',
            'created_at' => Carbon::parse('2026-09-21 10:00:00'),
        ]);
        $graceEvent = $this->event($grace, [
            'action' => 'holiday_created',
            'source' => 'employee_personal_link',
            'actor_name' => 'Grace Hopper',
            'actor_email' => 'grace@example.com',
            'created_at' => Carbon::parse('2026-09-23 10:00:00'),
        ]);

        foreach ([
            '/employee-audit?employee=ada' => $adaEvent->id,
            '/employee-audit?actor=alice%40example.com' => $adaEvent->id,
            '/employee-audit?action=holiday_created' => $graceEvent->id,
            '/employee-audit?source=employee_personal_link' => $graceEvent->id,
        ] as $url => $expectedId) {
            $this->actingAs($admin)->get($url)
                ->assertInertia(fn (Assert $page) => $page
                    ->has('events.data', 1)
                    ->where('events.data.0.id', $expectedId)
                );
        }

        $this->actingAs($admin)->get('/employee-audit?employee=ada&actor=alice&action=updated&source=user')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.employee', 'ada')
                ->where('filters.actor', 'alice')
                ->where('filters.action', 'updated')
                ->where('filters.source', 'user')
            );
    }

    public function test_audit_log_keeps_a_readable_identity_when_the_employee_no_longer_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['first_name' => 'Removed', 'last_name' => 'Employee']);
        $event = $this->event($employee, [
            'action' => 'import_removed',
            'old_values' => ['first_name' => 'Removed', 'last_name' => 'Employee'],
            'new_values' => [],
        ]);
        $employee->delete();

        $this->actingAs($admin)->get('/employee-audit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.data.0.id', $event->id)
                ->where('events.data.0.employee_name', 'Removed Employee')
            );

        $this->actingAs($admin)->get('/employee-audit?employee=removed')
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 1)
                ->where('events.data.0.id', $event->id)
            );
    }

    private function event(Employee $employee, array $overrides = []): EmployeeAuditEvent
    {
        return EmployeeAuditEvent::create([
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_email' => $employee->email,
            'action' => 'updated',
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
            'source' => 'user',
            'actor_type' => 'user',
            'actor_id' => null,
            'actor_name' => 'Admin',
            'actor_email' => 'admin@example.com',
            'actor_role' => User::ROLE_ADMIN,
            'old_values' => ['weekly_hours' => 24],
            'new_values' => ['weekly_hours' => 28],
            ...$overrides,
        ]);
    }
}
