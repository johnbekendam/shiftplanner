<?php

namespace Tests\Feature;

use App\Models\AvailabilityQuestion;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeConfigurationAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->employee = Employee::factory()->create();
        $this->actingAs($this->manager);
    }

    public function test_holiday_creation_and_removal_are_audited(): void
    {
        $this->post("/employees/{$this->employee->id}/holidays", [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'note' => 'Leave',
        ]);

        $holiday = $this->employee->holidays()->sole();
        $created = EmployeeAuditEvent::sole();
        $this->assertSame('holiday_created', $created->action);
        $this->assertSame([], $created->old_values);
        $this->assertSame('2026-10-01', $created->new_values['start_date']);

        $this->delete("/employees/{$this->employee->id}/holidays/{$holiday->id}");

        $removed = EmployeeAuditEvent::latest('id')->firstOrFail();
        $this->assertSame('holiday_removed', $removed->action);
        $this->assertSame('2026-10-01', $removed->old_values['start_date']);
        $this->assertSame([], $removed->new_values);
    }

    public function test_recurring_availability_change_is_audited(): void
    {
        $shift = Shift::factory()->create(['visible_by_default' => true]);

        $this->put("/employees/{$this->employee->id}/availability/2/{$shift->id}", [
            'level' => 'unavailable',
        ]);

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('availability_changed', $event->action);
        $this->assertSame([], $event->old_values);
        $this->assertSame('unavailable', $event->new_values['level']);
    }

    public function test_competence_attachment_is_audited(): void
    {
        $competence = Competence::factory()->create(['name' => 'Forklift']);

        $this->put("/employees/{$this->employee->id}/competences/{$competence->id}");

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('competence_attached', $event->action);
        $this->assertSame(['attached' => false], $event->old_values);
        $this->assertSame(['attached' => true], $event->new_values);
    }

    public function test_workcenter_mode_change_is_audited(): void
    {
        $workcenter = Workcenter::factory()->create();

        $this->put("/employees/{$this->employee->id}/workcenters/{$workcenter->id}", ['mode' => 'soft']);

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('workcenter_changed', $event->action);
        $this->assertSame(['mode' => null], $event->old_values);
        $this->assertSame(['mode' => 'soft'], $event->new_values);
    }

    public function test_question_answer_change_is_audited(): void
    {
        $question = AvailabilityQuestion::factory()->create();

        $this->put("/employees/{$this->employee->id}/questions/{$question->id}", ['answer' => true]);

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('question_answer_changed', $event->action);
        $this->assertSame(['answer' => false], $event->old_values);
        $this->assertSame(['answer' => true], $event->new_values);
    }
}