<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkcenterShiftTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_attach_a_shift(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put("/settings/workcenters/{$workcenter->id}/shifts", ['shift_ids' => [$shift->id]])
            ->assertRedirect('/login');
        $this->assertSame(0, $workcenter->shifts()->count());
    }

    public function test_manager_cannot_attach_a_shift(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put("/settings/workcenters/{$workcenter->id}/shifts", ['shift_ids' => [$shift->id]])
            ->assertForbidden();
    }

    // ── Attaching ──────────────────────────────────────────────────────

    public function test_attaching_a_shift_creates_seven_zero_spot_capacity_rows(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put("/settings/workcenters/{$workcenter->id}/shifts", ['shift_ids' => [$shift->id]])
            ->assertRedirect();

        $this->assertSame(1, $workcenter->shifts()->count());
        $this->assertSame(7, WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->count());
        $this->assertSame(0, WorkcenterShiftCapacity::query()
            ->where('workcenter_id', $workcenter->id)
            ->where('shift_id', $shift->id)
            ->sum('spots'));
    }

    public function test_an_already_attached_shift_keeps_its_capacity_rows(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'weekday' => 1,
            'spots' => 5,
        ]);

        $this->put("/settings/workcenters/{$workcenter->id}/shifts", ['shift_ids' => [$shift->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('workcenter_shift_capacities', [
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'weekday' => 1,
            'spots' => 5,
        ]);
    }

    // ── Detaching ──────────────────────────────────────────────────────

    public function test_detaching_a_shift_deletes_its_capacity_and_override_rows(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 1, 'spots' => 3,
        ]);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-12-24', 'spots' => 1,
        ]);

        $this->put("/settings/workcenters/{$workcenter->id}/shifts", ['shift_ids' => []])
            ->assertRedirect();

        $this->assertSame(0, $workcenter->shifts()->count());
        $this->assertSame(0, WorkcenterShiftCapacity::query()->where('workcenter_id', $workcenter->id)->count());
        $this->assertSame(0, WorkcenterShiftDateOverride::query()->where('workcenter_id', $workcenter->id)->count());
    }

    public function test_an_unlisted_shift_id_is_rejected(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();

        $this->put("/settings/workcenters/{$workcenter->id}/shifts", ['shift_ids' => [999999]])
            ->assertSessionHasErrors('shift_ids.0');
    }

    // ── Capacity ───────────────────────────────────────────────────────

    public function test_capacity_update_writes_all_seven_weekday_values(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);
        foreach (range(1, 7) as $weekday) {
            WorkcenterShiftCapacity::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $weekday, 'spots' => 0,
            ]);
        }

        $spots = [4, 4, 4, 4, 2, 0, 0];
        $this->put("/settings/workcenters/{$workcenter->id}/shifts/{$shift->id}/capacity", ['spots' => $spots])
            ->assertRedirect();

        foreach ($spots as $index => $expected) {
            $this->assertDatabaseHas('workcenter_shift_capacities', [
                'workcenter_id' => $workcenter->id,
                'shift_id' => $shift->id,
                'weekday' => $index + 1,
                'spots' => $expected,
            ]);
        }
    }

    public function test_capacity_update_is_rejected_for_a_shift_not_attached_to_the_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put("/settings/workcenters/{$workcenter->id}/shifts/{$shift->id}/capacity", ['spots' => array_fill(0, 7, 1)])
            ->assertSessionHasErrors('shift');
    }

    public function test_a_negative_spots_value_is_rejected(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);

        $spots = [4, 4, 4, 4, -1, 0, 0];
        $this->put("/settings/workcenters/{$workcenter->id}/shifts/{$shift->id}/capacity", ['spots' => $spots])
            ->assertSessionHasErrors('spots.4');
    }

    public function test_a_short_spots_array_is_rejected(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);

        $this->put("/settings/workcenters/{$workcenter->id}/shifts/{$shift->id}/capacity", ['spots' => [1, 2, 3]])
            ->assertSessionHasErrors('spots');
    }

    // ── spotsFor ───────────────────────────────────────────────────────

    public function test_spots_for_returns_the_weekday_default_with_no_override(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 2, 'spots' => 3,
        ]);

        $tuesday = Carbon::parse('2026-09-15'); // a Tuesday
        $this->assertSame(3, $workcenter->spotsFor($shift, $tuesday));
    }

    public function test_spots_for_returns_the_override_when_one_exists(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $date = Carbon::parse('2026-12-24');
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => $date->isoWeekday(), 'spots' => 4,
        ]);
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => $date, 'spots' => 1,
        ]);

        $this->assertSame(1, $workcenter->spotsFor($shift, $date));
    }

    public function test_spots_for_returns_zero_when_neither_row_exists(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->assertSame(0, $workcenter->spotsFor($shift, Carbon::parse('2026-09-15')));
    }
}
