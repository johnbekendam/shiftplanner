<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\PlanningRule;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each scenario starts from a valid assignment and breaks one constraint.
 * The week under test runs Monday 2026-09-14 to Sunday 2026-09-20.
 */
class PlanningVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK_START = '2026-09-14';

    private const TUESDAY = '2026-09-15';

    private Workcenter $workcenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->workcenter = Workcenter::factory()->create();
    }

    private function shift(string $start = '06:00', string $end = '14:00', bool $visible = true): Shift
    {
        $shift = Shift::factory()->create(['start_time' => $start, 'end_time' => $end, 'visible_by_default' => $visible]);
        $this->workcenter->shifts()->attach($shift);
        foreach (range(1, 7) as $weekday) {
            WorkcenterShiftCapacity::query()->create([
                'workcenter_id' => $this->workcenter->id, 'shift_id' => $shift->id, 'weekday' => $weekday, 'spots' => 5,
            ]);
        }

        return $shift;
    }

    /** A confirmed workcenter member, available for $shift on every weekday. */
    private function employee(Shift $shift, array $overrides = []): Employee
    {
        $employee = Employee::factory()->create(array_merge(['confirmed' => true, 'weekly_hours' => 40], $overrides));
        $employee->workcenters()->attach($this->workcenter);
        $this->makeAvailable($employee, $shift);

        return $employee;
    }

    private function makeAvailable(Employee $employee, Shift $shift, string $level = 'available'): void
    {
        foreach (range(1, 7) as $weekday) {
            RecurringAvailability::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'shift_id' => $shift->id, 'weekday' => $weekday],
                ['level' => $level],
            );
        }
    }

    private function assign(Employee $employee, Shift $shift, string $date = self::TUESDAY, ?Workcenter $workcenter = null): ShiftAssignment
    {
        return ShiftAssignment::factory()->create([
            'employee_id' => $employee->id, 'workcenter_id' => ($workcenter ?? $this->workcenter)->id,
            'shift_id' => $shift->id, 'date' => $date,
        ]);
    }

    /** @return array<int|string, string[]> assignment id => violation codes */
    private function verify(string $weekStart = self::WEEK_START): array
    {
        return $this->getJson("/planning/verify?week_start={$weekStart}")->assertOk()->json('violations');
    }

    private function codesFor(ShiftAssignment $assignment): array
    {
        return $this->verify()[$assignment->id] ?? [];
    }

    // ── Access and shape ────────────────────────────────────────────────

    public function test_a_manager_cannot_verify(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/planning/verify?week_start='.self::WEEK_START)->assertForbidden();
    }

    public function test_week_start_is_required(): void
    {
        $this->getJson('/planning/verify')->assertUnprocessable();
    }

    public function test_a_valid_assignment_has_no_violations(): void
    {
        $shift = $this->shift();
        $this->assign($this->employee($shift), $shift);

        $this->assertSame([], $this->verify());
    }

    public function test_only_assignments_in_the_week_are_checked(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        $this->makeAvailable($employee, $shift, 'unavailable');
        $this->assign($employee, $shift, '2026-09-13'); // Sunday of the previous week
        $this->assign($employee, $shift, '2026-09-21'); // Monday of the next week

        $this->assertSame([], $this->verify());
    }

    public function test_an_assignment_lists_every_violation(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift, ['confirmed' => false]);
        $this->makeAvailable($employee, $shift, 'unavailable');
        $assignment = $this->assign($employee, $shift);

        $this->assertEqualsCanonicalizing(['unconfirmed', 'unavailable'], $this->codesFor($assignment));
    }

    // ── Single-assignment checks ────────────────────────────────────────

    public function test_an_unconfirmed_employee(): void
    {
        $shift = $this->shift();
        $assignment = $this->assign($this->employee($shift, ['confirmed' => false]), $shift);

        $this->assertSame(['unconfirmed'], $this->codesFor($assignment));
    }

    public function test_an_archived_employee(): void
    {
        $shift = $this->shift();
        $assignment = $this->assign($this->employee($shift, ['archived_at' => now()]), $shift);

        $this->assertSame(['archived'], $this->codesFor($assignment));
    }

    public function test_an_employee_on_holiday(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        EmployeeHoliday::query()->create(['employee_id' => $employee->id, 'start_date' => '2026-09-15', 'end_date' => '2026-09-16']);
        $assignment = $this->assign($employee, $shift);

        $this->assertSame(['holiday'], $this->codesFor($assignment));
    }

    public function test_an_unavailable_cell(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        $this->makeAvailable($employee, $shift, 'unavailable');
        $assignment = $this->assign($employee, $shift);

        $this->assertSame(['unavailable'], $this->codesFor($assignment));
    }

    public function test_a_missing_availability_row_counts_as_unavailable(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        RecurringAvailability::query()->where('employee_id', $employee->id)->delete();
        $assignment = $this->assign($employee, $shift);

        $this->assertSame(['unavailable'], $this->codesFor($assignment));
    }

    public function test_a_not_preferred_cell_breaks_only_a_hard_rule(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        $this->makeAvailable($employee, $shift, 'not_preferred');
        $assignment = $this->assign($employee, $shift);
        $rule = PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);

        $this->assertSame([], $this->codesFor($assignment));

        $rule->update(['mode' => 'hard', 'severity' => null]);

        $this->assertSame(['not_preferred'], $this->codesFor($assignment));
    }

    public function test_an_employee_outside_the_workcenter(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        $employee->workcenters()->detach();
        $assignment = $this->assign($employee, $shift);

        $this->assertSame(['workcenter_ineligible'], $this->codesFor($assignment));
    }

    public function test_a_hidden_shift_the_workcenter_does_not_run(): void
    {
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00', 'visible_by_default' => false]);
        $assignment = $this->assign($this->employee($shift), $shift);

        $this->assertSame(['shift_hidden'], $this->codesFor($assignment));
    }

    public function test_a_hidden_shift_the_workcenter_runs_is_allowed(): void
    {
        $shift = $this->shift(visible: false);
        $assignment = $this->assign($this->employee($shift), $shift);

        $this->assertSame([], $this->codesFor($assignment));
    }

    public function test_a_missing_hard_competence(): void
    {
        $shift = $this->shift();
        $employee = $this->employee($shift);
        $competence = Competence::factory()->create();
        $assignment = $this->assign($employee, $shift);
        $rule = PlanningRule::create([
            'type' => 'competence_required', 'mode' => 'soft', 'severity' => 5,
            'config' => ['workcenter_id' => $this->workcenter->id, 'competence_id' => $competence->id],
        ]);

        $this->assertSame([], $this->codesFor($assignment), 'a soft rule is not a violation');

        $rule->update(['mode' => 'hard', 'severity' => null]);
        $this->assertSame(['competence_required'], $this->codesFor($assignment));

        $employee->competences()->attach($competence);
        $this->assertSame([], $this->codesFor($assignment));
    }

    public function test_a_hard_business_line_mismatch(): void
    {
        $shift = $this->shift();
        [$required, $other] = BusinessLine::factory()->count(2)->create();
        $employee = $this->employee($shift, ['business_line_id' => $other->id]);
        $assignment = $this->assign($employee, $shift);
        PlanningRule::create([
            'type' => 'business_line_preference', 'mode' => 'hard',
            'config' => ['workcenter_id' => $this->workcenter->id, 'business_line_id' => $required->id],
        ]);

        $this->assertSame(['business_line_preference'], $this->codesFor($assignment));

        $employee->update(['business_line_id' => $required->id]);
        $this->assertSame([], $this->codesFor($assignment));
    }
}
