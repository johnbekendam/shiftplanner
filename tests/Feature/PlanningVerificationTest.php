<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\PlanningRule;
use App\Models\PlanningSettings;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
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

        // The workcenter does not run the shift, so the cell also has 0 spots.
        $this->assertEqualsCanonicalizing(['shift_hidden', 'cell_overfilled'], $this->codesFor($assignment));
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

    // ── Group checks ────────────────────────────────────────────────────

    public function test_overlapping_assignments_are_both_marked(): void
    {
        $early = $this->shift('06:00', '14:00');
        $mid = $this->shift('10:00', '18:00');
        $employee = $this->employee($early);
        $this->makeAvailable($employee, $mid);
        $first = $this->assign($employee, $early);
        $second = $this->assign($employee, $mid);

        $violations = $this->verify();

        $this->assertSame(['overlap'], $violations[$first->id]);
        $this->assertSame(['overlap'], $violations[$second->id]);
    }

    public function test_a_hard_daily_cap_marks_every_shift_that_day(): void
    {
        $early = $this->shift('06:00', '14:00');
        $late = $this->shift('14:00', '22:00');
        $employee = $this->employee($early);
        $this->makeAvailable($employee, $late);
        $first = $this->assign($employee, $early);
        $second = $this->assign($employee, $late);
        $other = $this->assign($employee, $early, '2026-09-16');
        $rule = PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 5, 'config' => ['value' => 1]]);

        $this->assertSame([], $this->verify(), 'a soft cap is not a violation');

        $rule->update(['mode' => 'hard', 'severity' => null]);
        $violations = $this->verify();

        $this->assertSame(['max_shifts_per_day'], $violations[$first->id]);
        $this->assertSame(['max_shifts_per_day'], $violations[$second->id]);
        $this->assertArrayNotHasKey($other->id, $violations);
    }

    public function test_the_week_hours_cap_marks_every_shift_of_the_employee_that_week(): void
    {
        PlanningSettings::current()->update(['period_start' => self::WEEK_START]);
        $shift = $this->shift('06:00', '14:00');
        $employee = $this->employee($shift, ['weekly_hours' => 8]);
        $first = $this->assign($employee, $shift);
        $second = $this->assign($employee, $shift, '2026-09-16');
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $violations = $this->verify();

        // 16h in the week is above 8h + 4h; the 16h cycle total is not above 2 x 8h.
        $this->assertSame(['max_hours_per_week_distribution'], $violations[$first->id]);
        $this->assertSame(['max_hours_per_week_distribution'], $violations[$second->id]);
    }

    public function test_the_cycle_hours_cap_counts_the_other_week_of_the_cycle(): void
    {
        PlanningSettings::current()->update(['period_start' => self::WEEK_START]);
        $shift = $this->shift('06:00', '14:00');
        $employee = $this->employee($shift, ['weekly_hours' => 8]);
        $inWeek = $this->assign($employee, $shift);
        $this->assign($employee, $shift, '2026-09-22');
        $this->assign($employee, $shift, '2026-09-23');
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $violations = $this->verify();

        // 24h in the cycle is above 2 x 8h; the week itself holds only 8h.
        $this->assertSame(['max_hours_per_week'], $violations[$inWeek->id]);
    }

    public function test_hours_caps_count_shifts_in_other_workcenters(): void
    {
        PlanningSettings::current()->update(['period_start' => self::WEEK_START]);
        $other = Workcenter::factory()->create();
        $shift = $this->shift('06:00', '14:00');
        $employee = $this->employee($shift, ['weekly_hours' => 8]);
        $employee->workcenters()->attach($other);
        $here = $this->assign($employee, $shift);
        $this->assign($employee, $shift, '2026-09-16', $other);
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->assertSame(['max_hours_per_week_distribution'], $this->codesFor($here));
    }

    public function test_an_alternating_pair_in_one_week_marks_both_pair_shifts(): void
    {
        $early = $this->shift('06:00', '14:00');
        $late = $this->shift('14:00', '22:00');
        $mid = $this->shift('10:00', '18:00');
        $employee = $this->employee($early);
        $this->makeAvailable($employee, $late);
        $this->makeAvailable($employee, $mid);
        $monday = $this->assign($employee, $early, self::WEEK_START);
        $wednesday = $this->assign($employee, $late, '2026-09-16');
        $friday = $this->assign($employee, $mid, '2026-09-18');
        $nextWeek = $this->assign($employee, $late, '2026-09-22');
        PlanningRule::create([
            'type' => 'alternating_shift_pair', 'mode' => 'hard',
            'config' => ['first_shift_id' => $early->id, 'second_shift_id' => $late->id],
        ]);

        $violations = $this->verify();

        $this->assertSame(['alternating_shift_pair'], $violations[$monday->id]);
        $this->assertSame(['alternating_shift_pair'], $violations[$wednesday->id]);
        $this->assertArrayNotHasKey($friday->id, $violations);
        $this->assertArrayNotHasKey($nextWeek->id, $violations);
    }

    public function test_an_overfilled_cell_marks_every_assignment_in_it(): void
    {
        $shift = $this->shift();
        $first = $this->assign($this->employee($shift), $shift);
        $second = $this->assign($this->employee($shift), $shift);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $this->workcenter->id, 'shift_id' => $shift->id, 'date' => self::TUESDAY, 'spots' => 1,
        ]);

        $violations = $this->verify();

        $this->assertSame(['cell_overfilled'], $violations[$first->id]);
        $this->assertSame(['cell_overfilled'], $violations[$second->id]);
    }
}
