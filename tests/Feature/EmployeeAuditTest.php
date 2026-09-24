<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}