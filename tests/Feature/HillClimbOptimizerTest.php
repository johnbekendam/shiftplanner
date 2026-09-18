<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\Employee;
use App\Models\PlanGenerationRun;
use App\Models\PlanningRule;
use App\Models\PublishedWeek;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use App\Services\Planning\HeuristicPlanGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HillClimbOptimizerTest extends TestCase
{
    use RefreshDatabase;

    private const CYCLE_START = '2026-09-07'; // a Monday

    private function generator(): HeuristicPlanGenerator
    {
        return app(HeuristicPlanGenerator::class);
    }

    private function makeRun(string $cycleStart = self::CYCLE_START): PlanGenerationRun
    {
        return PlanGenerationRun::create(['cycle_start' => $cycleStart, 'status' => PlanGenerationRun::STATUS_PENDING]);
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge(['confirmed' => true, 'weekly_hours' => 40], $overrides));
    }

    private function makeAvailable(Employee $employee, Shift $shift, string $date, string $level = 'available'): void
    {
        RecurringAvailability::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'weekday' => Carbon::parse($date)->isoWeekday(),
            'level' => $level,
        ]);
    }

    private function openCell(Workcenter $workcenter, Shift $shift, string $date, int $spots = 1): void
    {
        $workcenter->shifts()->attach($shift, []);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => $spots,
        ]);
    }

    public function test_equal_workload_rebalances_a_fully_staffed_cycle_via_substitute(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']); // 8h
        $workcenter->shifts()->attach($shift);

        $a = $this->employee();
        $b = $this->employee();
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            $this->makeAvailable($a, $shift, $date);
            $this->makeAvailable($b, $shift, $date);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
            ]);
        }
        // Both spots already fully staffed by A alone — nothing is "open" for construction.
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'fixed' => false,
        ]);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertSame(1, ShiftAssignment::where('employee_id', $a->id)->count());
        $this->assertSame(1, ShiftAssignment::where('employee_id', $b->id)->count());
    }

    public function test_a_fixed_assignment_is_never_substituted_away_even_to_improve_fairness(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);

        $a = $this->employee();
        $b = $this->employee();
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            $this->makeAvailable($a, $shift, $date);
            $this->makeAvailable($b, $shift, $date);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
            ]);
        }
        $fixed = ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'fixed' => true,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'fixed' => false,
        ]);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $fixed->id, 'employee_id' => $a->id]);
    }

    public function test_a_published_assignment_is_never_substituted_away(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach($shift);

        $a = $this->employee();
        $b = $this->employee();
        foreach (['2026-09-08', '2026-09-09'] as $date) {
            $this->makeAvailable($a, $shift, $date);
            $this->makeAvailable($b, $shift, $date);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
            ]);
        }
        $published = ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $a->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-09', 'fixed' => false,
        ]);
        PublishedWeek::query()->create(['week_start' => self::CYCLE_START, 'workcenter_id' => $workcenter->id]);
        PlanningRule::create(['type' => 'equal_workload']);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['id' => $published->id, 'employee_id' => $a->id]);
    }

    public function test_not_preferred_shift_is_avoided_in_favor_of_an_available_candidate(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');

        // Created first (lower id), so construction's stable tie-break would pick it
        // if soft preference weren't considered at all.
        $notPreferred = $this->employee();
        $this->makeAvailable($notPreferred, $shift, '2026-09-08', 'not_preferred');
        $available = $this->employee();
        $this->makeAvailable($available, $shift, '2026-09-08', 'available');

        PlanningRule::create(['type' => 'not_preferred_shift', 'mode' => 'soft', 'severity' => 5]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $available->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $notPreferred->id]);
    }

    public function test_soft_max_shifts_per_day_prefers_the_candidate_under_the_cap(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shiftX1 = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '08:00']);
        $shiftX2 = Shift::factory()->create(['start_time' => '08:00', 'end_time' => '10:00']);
        $shiftY = Shift::factory()->create(['start_time' => '10:00', 'end_time' => '12:00']);
        $shiftZ = Shift::factory()->create(['start_time' => '12:00', 'end_time' => '14:00']);
        $workcenter->shifts()->attach([$shiftX1->id, $shiftX2->id, $shiftZ->id]);

        $atCap = $this->employee(); // already 2 shifts that day
        $underCap = $this->employee(); // already 1 shift that day
        ShiftAssignment::factory()->create([
            'employee_id' => $atCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shiftX1->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $atCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shiftX2->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        ShiftAssignment::factory()->create([
            'employee_id' => $underCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $shiftZ->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        $this->makeAvailable($atCap, $shiftY, '2026-09-08');
        $this->makeAvailable($underCap, $shiftY, '2026-09-08');
        $this->openCell($workcenter, $shiftY, '2026-09-08');
        WorkcenterShiftDateOverride::query()->where('shift_id', $shiftY->id)->update(['spots' => 1]);

        PlanningRule::create(['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 10, 'config' => ['value' => 2]]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $underCap->id, 'shift_id' => $shiftY->id, 'date' => '2026-09-08',
        ]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $atCap->id, 'shift_id' => $shiftY->id]);
    }

    public function test_soft_max_hours_per_week_prefers_the_candidate_under_the_cap(): void
    {
        $workcenter = Workcenter::factory()->create();
        $existingShift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']); // 8h
        $openShift = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']); // 8h
        $workcenter->shifts()->attach($existingShift->id);

        // weekly_hours 4 -> cap 8h over the cycle. atCap already holds exactly 8h.
        $atCap = $this->employee(['weekly_hours' => 4]);
        $underCap = $this->employee(['weekly_hours' => 4]);
        ShiftAssignment::factory()->create([
            'employee_id' => $atCap->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $existingShift->id, 'date' => '2026-09-08', 'fixed' => false,
        ]);
        $this->makeAvailable($atCap, $openShift, '2026-09-08');
        $this->makeAvailable($underCap, $openShift, '2026-09-08');
        $this->openCell($workcenter, $openShift, '2026-09-08');

        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'soft', 'severity' => 10]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', [
            'employee_id' => $underCap->id, 'shift_id' => $openShift->id, 'date' => '2026-09-08',
        ]);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $atCap->id, 'shift_id' => $openShift->id]);
    }

    public function test_alternating_shift_pair_is_honored_against_the_previous_week(): void
    {
        $workcenter = Workcenter::factory()->create();
        $early = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $late = Shift::factory()->create(['start_time' => '14:00', 'end_time' => '22:00']);
        $this->openCell($workcenter, $early, '2026-09-07');
        $this->openCell($workcenter, $late, '2026-09-07');

        $e1 = $this->employee();
        $e2 = $this->employee();
        foreach ([$early, $late] as $shift) {
            $this->makeAvailable($e1, $shift, '2026-09-07');
            $this->makeAvailable($e2, $shift, '2026-09-07');
        }
        // The Monday one week before the cycle: e1 worked Early (exactly one pair
        // member), so the opposite (Late) is preferred for e1 on the cycle's Monday.
        ShiftAssignment::factory()->create([
            'employee_id' => $e1->id, 'workcenter_id' => $workcenter->id, 'shift_id' => $early->id, 'date' => '2026-08-31', 'fixed' => false,
        ]);

        PlanningRule::create([
            'type' => 'alternating_shift_pair', 'mode' => 'soft', 'severity' => 10,
            'config' => ['first_shift_id' => $early->id, 'second_shift_id' => $late->id],
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        // e1 must not hold Early without also holding Late.
        $e1HasEarly = ShiftAssignment::where(['employee_id' => $e1->id, 'shift_id' => $early->id, 'date' => '2026-09-07'])->exists();
        $e1HasLate = ShiftAssignment::where(['employee_id' => $e1->id, 'shift_id' => $late->id, 'date' => '2026-09-07'])->exists();
        $this->assertFalse($e1HasEarly && ! $e1HasLate, 'e1 repeated Early without the preferred opposite (Late).');
        // Both spots are still covered either way.
        $this->assertSame(2, ShiftAssignment::whereDate('date', '2026-09-07')->count());
    }

    public function test_soft_competence_required_prefers_the_candidate_who_holds_it(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');
        $competence = Competence::factory()->create();

        $holder = $this->employee();
        $holder->competences()->attach($competence->id);
        $this->makeAvailable($holder, $shift, '2026-09-08');
        $nonHolder = $this->employee();
        $this->makeAvailable($nonHolder, $shift, '2026-09-08');

        PlanningRule::create([
            'type' => 'competence_required', 'mode' => 'soft', 'severity' => 10,
            'config' => ['workcenter_id' => $workcenter->id, 'competence_id' => $competence->id],
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $holder->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $nonHolder->id]);
    }

    public function test_soft_business_line_preference_prefers_the_matching_candidate(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create(['start_time' => '06:00', 'end_time' => '14:00']);
        $this->openCell($workcenter, $shift, '2026-09-08');
        $line = BusinessLine::factory()->create();

        $matching = $this->employee(['business_line_id' => $line->id]);
        $this->makeAvailable($matching, $shift, '2026-09-08');
        $other = $this->employee();
        $this->makeAvailable($other, $shift, '2026-09-08');

        PlanningRule::create([
            'type' => 'business_line_preference', 'mode' => 'soft', 'severity' => 10,
            'config' => ['workcenter_id' => $workcenter->id, 'business_line_id' => $line->id],
        ]);

        $run = $this->makeRun();
        $this->generator()->generate($run);

        $this->assertDatabaseHas('shift_assignments', ['employee_id' => $matching->id, 'date' => '2026-09-08']);
        $this->assertDatabaseMissing('shift_assignments', ['employee_id' => $other->id]);
    }
}
