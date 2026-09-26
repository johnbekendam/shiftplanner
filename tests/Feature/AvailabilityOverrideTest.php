<?php

namespace Tests\Feature;

use App\Models\AvailabilityOverride;
use App\Models\Employee;
use App\Models\EmployeeAuditEvent;
use App\Models\PlanningSettings;
use App\Models\RecurringAvailability;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityOverrideTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2026-10-05';

    private function linkedEmployee(): array
    {
        $employee = Employee::factory()->create(['confirmed' => true]);
        $link = $employee->personalLink()->create(['token' => 'tok-'.$employee->id]);

        return [$employee, $link->token];
    }

    private function adminUrl(Employee $employee, string $date = self::MONDAY): string
    {
        return "/employees/{$employee->id}/availability/dates/{$date}";
    }

    private function rows(Employee $employee): array
    {
        return $employee->availabilityOverrides()->orderBy('shift_id')->get()->map->toPayload()->all();
    }

    public function test_the_admin_sets_shift_overrides_and_a_whole_day_block(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $early = Shift::factory()->create(['visible_by_default' => true]);
        $late = Shift::factory()->create(['visible_by_default' => true]);

        $this->put($this->adminUrl($employee), [
            'blocked' => false,
            'shifts' => [$early->id => 'available', $late->id => 'not_preferred'],
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            ['date' => self::MONDAY, 'shift_id' => $early->id, 'level' => 'available'],
            ['date' => self::MONDAY, 'shift_id' => $late->id, 'level' => 'not_preferred'],
        ], $this->rows($employee));

        $this->put($this->adminUrl($employee), ['blocked' => true, 'shifts' => [$early->id => 'available']])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            ['date' => self::MONDAY, 'shift_id' => null, 'level' => 'unavailable'],
            ['date' => self::MONDAY, 'shift_id' => $early->id, 'level' => 'available'],
        ], $this->rows($employee));
    }

    public function test_an_empty_payload_resets_the_date_to_the_default(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['visible_by_default' => true]);
        $employee->availabilityOverrides()->create(['date' => self::MONDAY, 'shift_id' => $shift->id, 'level' => 'available']);
        $employee->availabilityOverrides()->create(['date' => '2026-10-06', 'shift_id' => $shift->id, 'level' => 'available']);

        $this->put($this->adminUrl($employee), ['blocked' => false, 'shifts' => []])->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-06'], $employee->availabilityOverrides()->get()->map->toPayload()->pluck('date')->all());
    }

    public function test_a_shift_outside_the_effective_set_or_an_invalid_level_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();
        $hidden = Shift::factory()->create(['visible_by_default' => false]);
        $visible = Shift::factory()->create(['visible_by_default' => true]);

        $this->put($this->adminUrl($employee), ['blocked' => false, 'shifts' => [$hidden->id => 'available']])
            ->assertSessionHasErrors('shifts');
        $this->put($this->adminUrl($employee), ['blocked' => false, 'shifts' => [$visible->id => 'maybe']])
            ->assertSessionHasErrors();
        $this->put($this->adminUrl($employee, '05-10-2026'), ['blocked' => false, 'shifts' => []])
            ->assertNotFound();

        $this->assertSame(0, AvailabilityOverride::count());
    }

    public function test_the_change_is_audited_once_and_not_when_nothing_changes(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::factory()->create();

        $this->put($this->adminUrl($employee), ['blocked' => true, 'shifts' => []]);
        $this->put($this->adminUrl($employee), ['blocked' => true, 'shifts' => []]);

        $event = EmployeeAuditEvent::sole();
        $this->assertSame('availability_changed', $event->action);
        $this->assertSame(['date' => self::MONDAY, 'blocked' => false, 'shifts' => []], $event->old_values);
        $this->assertSame(['date' => self::MONDAY, 'blocked' => true, 'shifts' => []], $event->new_values);
    }

    public function test_the_employee_sets_overrides_on_their_personal_page(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $shift = Shift::factory()->create(['visible_by_default' => true]);

        $this->put("/personal/{$token}/availability/dates/".self::MONDAY, [
            'blocked' => false, 'shifts' => [$shift->id => 'unavailable'],
        ])->assertRedirect("/personal/{$token}");

        $this->assertSame([['date' => self::MONDAY, 'shift_id' => $shift->id, 'level' => 'unavailable']], $this->rows($employee));
        $this->assertSame('employee', EmployeeAuditEvent::sole()->actor_type);
    }

    public function test_the_change_lock_blocks_personal_overrides(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        PlanningSettings::current()->update(['allow_employee_changes' => false]);

        $this->put("/personal/{$token}/availability/dates/".self::MONDAY, ['blocked' => true, 'shifts' => []])
            ->assertStatus(403);

        $this->assertSame(0, AvailabilityOverride::count());
    }

    public function test_both_pages_include_every_override(): void
    {
        [$employee, $token] = $this->linkedEmployee();
        $employee->availabilityOverrides()->create(['date' => self::MONDAY, 'shift_id' => null, 'level' => 'unavailable']);
        $employee->availabilityOverrides()->create(['date' => '2025-01-06', 'shift_id' => null, 'level' => 'unavailable']);

        $this->get("/personal/{$token}")->assertInertia(fn ($page) => $page->has('availabilityOverrides', 2));
        $this->actingAs(User::factory()->create())->get("/employees/{$employee->id}/edit")
            ->assertInertia(fn ($page) => $page->has('availabilityOverrides', 2)
                ->where('availabilityOverrides.0', ['date' => '2025-01-06', 'shift_id' => null, 'level' => 'unavailable']));
    }

    public function test_an_override_decides_manual_eligibility_for_that_date(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $employee = Employee::factory()->create(['confirmed' => true]);
        $workcenter = Workcenter::factory()->create();
        $employee->workcenters()->attach($workcenter);
        $shift = Shift::factory()->create();
        WorkcenterShiftCapacity::query()->create(['workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 1, 'spots' => 3]);
        RecurringAvailability::factory()->create(['employee_id' => $employee->id, 'weekday' => 1, 'shift_id' => $shift->id, 'level' => 'available']);
        $employee->availabilityOverrides()->create(['date' => self::MONDAY, 'shift_id' => $shift->id, 'level' => 'not_preferred']);
        $employee->availabilityOverrides()->create(['date' => '2026-10-12', 'shift_id' => null, 'level' => 'unavailable']);

        $entry = fn (string $date) => collect($this->get(
            "/planning/eligible-employees?workcenter_id={$workcenter->id}&shift_id={$shift->id}&date={$date}"
        )->json())->firstWhere('id', $employee->id);

        $this->assertNull($entry(self::MONDAY)['block_reason']);
        $this->assertTrue($entry(self::MONDAY)['not_preferred']);
        $this->assertSame('unavailable', $entry('2026-10-12')['block_reason']);
        $this->assertNull($entry('2026-10-19')['block_reason']);
    }
}
