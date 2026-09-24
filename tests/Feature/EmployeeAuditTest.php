<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\User;
use App\Services\SelfSignupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class EmployeeAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_creation_records_changed_values_and_actor_snapshot(): void
    {
        $user = User::factory()->create([
            'name' => 'Audit Manager',
            'email' => 'manager@example.com',
            'role' => User::ROLE_MANAGER,
        ]);

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'Silvio',
            'last_name' => 'Teixeira',
            'email' => 'silvio@example.com',
            'weekly_hours' => 32,
        ])->assertRedirect();

        $employee = Employee::firstWhere('email', 'silvio@example.com');
        $event = EmployeeAuditEvent::sole();

        $this->assertSame($employee->id, $event->employee_id);
        $this->assertSame('Silvio Teixeira', $event->employee_name);
        $this->assertSame('silvio@example.com', $event->employee_email);
        $this->assertSame('created', $event->action);
        $this->assertSame('employee', $event->subject_type);
        $this->assertSame($employee->id, $event->subject_id);
        $this->assertSame('user', $event->source);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame('Audit Manager', $event->actor_name);
        $this->assertSame('manager@example.com', $event->actor_email);
        $this->assertSame(User::ROLE_MANAGER, $event->actor_role);
        $this->assertSame([], $event->old_values);
        $this->assertSame([
            'first_name' => 'Silvio',
            'last_name' => 'Teixeira',
            'email' => 'silvio@example.com',
            'weekly_hours' => 32,
            'weekly_hours_minimum' => null,
            'business_line_id' => null,
            'confirmed' => false,
        ], $event->new_values);
    }

    public function test_employee_update_records_only_changed_values(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'weekly_hours' => 32,
        ]);

        $this->actingAs($user)->put("/employees/{$employee->id}", [
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => $employee->email,
            'weekly_hours' => 36,
        ])->assertRedirect();

        $event = EmployeeAuditEvent::sole();

        $this->assertSame('updated', $event->action);
        $this->assertSame(['first_name' => 'Old', 'weekly_hours' => 32], $event->old_values);
        $this->assertSame(['first_name' => 'New', 'weekly_hours' => 36], $event->new_values);
    }

    public function test_confirmation_change_records_an_event(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['confirmed' => false]);

        $this->actingAs($user)->put("/employees/{$employee->id}/confirmed", [
            'confirmed' => true,
        ])->assertRedirect();

        $event = EmployeeAuditEvent::sole();

        $this->assertSame('confirmation_changed', $event->action);
        $this->assertSame(['confirmed' => false], $event->old_values);
        $this->assertSame(['confirmed' => true], $event->new_values);
    }

    public function test_audit_events_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/employees', [
            'first_name' => 'Permanent',
            'last_name' => 'History',
            'email' => 'permanent@example.com',
            'weekly_hours' => 32,
        ]);

        $event = EmployeeAuditEvent::sole();

        try {
            $event->update(['action' => 'changed']);
            $this->fail('An audit event update did not throw an exception.');
        } catch (LogicException) {
            $this->assertDatabaseHas('employee_audit_events', [
                'id' => $event->id,
                'action' => 'created',
            ]);
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_public_signup_records_a_public_actor_snapshot(): void
    {
        Queue::fake();

        app(SelfSignupService::class)->register('Public', 'Person', 'public@example.com');

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('public_signup', $event->source);
        $this->assertSame('public', $event->actor_type);
        $this->assertSame('Public Person', $event->actor_name);
        $this->assertSame('public@example.com', $event->actor_email);
    }

    public function test_personal_link_update_records_the_employee_as_actor(): void
    {
        $employee = Employee::factory()->create(['weekly_hours' => 24]);
        $employee->personalLink()->create(['token' => 'audit-personal-token']);

        $this->put('/personal/audit-personal-token', [
            'weekly_hours' => 28,
            'business_line_id' => null,
        ])->assertRedirect();

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('employee_personal_link', $event->source);
        $this->assertSame('employee', $event->actor_type);
        $this->assertSame($employee->id, $event->actor_id);
        $this->assertSame(['weekly_hours' => 24], $event->old_values);
        $this->assertSame(['weekly_hours' => 28], $event->new_values);
    }

    public function test_account_link_records_the_authenticated_user(): void
    {
        $employee = Employee::factory()->create(['email' => 'link@example.com']);
        $user = User::factory()->create(['email' => 'link@example.com', 'employee_id' => null]);

        $this->actingAs($user)->post('/account/employee')->assertRedirect();

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('linked', $event->action);
        $this->assertSame('account_link', $event->source);
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame(['employee_id' => null], $event->old_values);
        $this->assertSame(['employee_id' => $employee->id], $event->new_values);
    }

    public function test_manager_can_view_an_archived_employee_audit_timeline_newest_first(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $employee = Employee::factory()->create(['archived_at' => now()]);
        EmployeeAuditEvent::create([
            'employee_id' => $employee->id,
            'action' => 'updated',
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
            'source' => 'user',
            'actor_type' => 'user',
            'actor_id' => $manager->id,
            'actor_name' => 'Manager Name',
            'actor_email' => 'manager@example.com',
            'actor_role' => User::ROLE_MANAGER,
            'old_values' => ['weekly_hours' => 24, 'confirmed' => false],
            'new_values' => ['weekly_hours' => 28, 'confirmed' => true],
            'created_at' => Carbon::parse('2026-09-23 10:00:00'),
        ]);
        $newest = EmployeeAuditEvent::create([
            'employee_id' => $employee->id,
            'action' => 'archived',
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
            'source' => 'user',
            'actor_type' => null,
            'actor_id' => null,
            'actor_name' => null,
            'actor_email' => null,
            'actor_role' => null,
            'old_values' => ['archived_at' => null],
            'new_values' => ['archived_at' => '2026-09-24T10:00:00+00:00'],
            'created_at' => Carbon::parse('2026-09-24 10:00:00'),
        ]);

        $this->actingAs($manager)->get("/employees/{$employee->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->has('auditEvents', 2)
                ->where('auditEvents.0.id', $newest->id)
                ->where('auditEvents.0.action', 'archived')
                ->where('auditEvents.0.subject_type', 'employee')
                ->where('auditEvents.0.subject_id', $employee->id)
                ->where('auditEvents.0.source', 'user')
                ->where('auditEvents.0.actor_type', null)
                ->where('auditEvents.0.actor_id', null)
                ->where('auditEvents.0.actor_name', null)
                ->where('auditEvents.0.actor_email', null)
                ->where('auditEvents.0.actor_role', null)
                ->where('auditEvents.0.old_values', ['archived_at' => null])
                ->where('auditEvents.0.new_values', ['archived_at' => '2026-09-24T10:00:00+00:00'])
                ->where('auditEvents.0.created_at', '2026-09-24T10:00:00+00:00')
                ->where('auditEvents.1.action', 'updated')
            );
    }

    public function test_guest_cannot_view_an_employee_audit_timeline(): void
    {
        $employee = Employee::factory()->create();

        $this->get("/employees/{$employee->id}/edit")->assertRedirect('/login');
    }
}
