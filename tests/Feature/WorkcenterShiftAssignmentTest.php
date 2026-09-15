<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkcenterShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_the_index(): void
    {
        $this->get('/schedule')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden_from_the_index(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/schedule')->assertForbidden();
    }

    public function test_guest_cannot_create_an_assignment(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/schedule', $this->validPayload($workcenter, $shift))->assertRedirect('/login');
        $this->assertSame(0, $workcenter->shifts()->count());
    }

    public function test_manager_cannot_create_an_assignment(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/schedule', $this->validPayload($workcenter, $shift))->assertForbidden();
    }

    // ── Index payload ───────────────────────────────────────────────────

    public function test_index_lists_active_workcenters_shifts_and_assignments(): void
    {
        $this->actingAsAdmin();
        $active = Workcenter::factory()->create(['name' => 'Line 1', 'position' => 1]);
        $archived = Workcenter::factory()->create(['name' => 'Line 0', 'position' => 0, 'archived_at' => now()]);
        $shift = Shift::factory()->create(['name' => 'Early']);
        $active->shifts()->attach($shift);
        WorkcenterShiftCapacity::query()->create([
            'workcenter_id' => $active->id, 'shift_id' => $shift->id, 'weekday' => 1, 'spots' => 4,
        ]);

        $this->get('/schedule')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkcenterShifts')
                ->has('workcenters', 1)
                ->where('workcenters.0.name', 'Line 1')
                ->has('shifts', 1)
                ->has('assignments', 1)
                ->where('assignments.0.workcenter_id', $active->id)
                ->where('assignments.0.shift_id', $shift->id)
                ->where('assignments.0.spots', [4, 0, 0, 0, 0, 0, 0])
            );

        $this->assertSame('Line 0', $archived->fresh()->name); // archived workcenter still exists, just excluded from the select
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_store_creates_the_pivot_and_seven_capacity_rows(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/schedule', $this->validPayload($workcenter, $shift, [4, 4, 4, 4, 2, 0, 0]))
            ->assertRedirect();

        $this->assertSame(1, $workcenter->shifts()->count());
        foreach ([4, 4, 4, 4, 2, 0, 0] as $index => $spots) {
            $this->assertDatabaseHas('workcenter_shift_capacities', [
                'workcenter_id' => $workcenter->id,
                'shift_id' => $shift->id,
                'weekday' => $index + 1,
                'spots' => $spots,
            ]);
        }
    }

    public function test_store_rejects_a_duplicate_pair(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);

        $this->post('/schedule', $this->validPayload($workcenter, $shift))
            ->assertSessionHasErrors('shift_id');
    }

    public function test_store_rejects_an_archived_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['archived_at' => now()]);
        $shift = Shift::factory()->create();

        $this->post('/schedule', $this->validPayload($workcenter, $shift))
            ->assertSessionHasErrors('workcenter_id');
    }

    public function test_store_rejects_a_missing_workcenter_or_shift(): void
    {
        $this->actingAsAdmin();
        $shift = Shift::factory()->create();

        $this->post('/schedule', [
            'workcenter_id' => 999999,
            'shift_id' => $shift->id,
            'spots' => array_fill(0, 7, 0),
        ])->assertSessionHasErrors('workcenter_id');
    }

    public function test_store_rejects_a_negative_spots_value(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/schedule', $this->validPayload($workcenter, $shift, [4, 4, 4, 4, -1, 0, 0]))
            ->assertSessionHasErrors('spots.4');
    }

    public function test_store_rejects_a_short_spots_array(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/schedule', [
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'spots' => [1, 2, 3],
        ])->assertSessionHasErrors('spots');
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function test_update_writes_all_seven_weekday_values(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $workcenter->shifts()->attach($shift);

        $spots = [4, 4, 4, 4, 2, 0, 0];
        $this->put("/schedule/{$workcenter->id}/{$shift->id}", ['spots' => $spots])
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

    public function test_update_404s_on_an_unassigned_pair(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put("/schedule/{$workcenter->id}/{$shift->id}", ['spots' => array_fill(0, 7, 1)])
            ->assertNotFound();
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function test_destroy_detaches_the_pivot_and_deletes_capacity_and_override_rows(): void
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

        $this->delete("/schedule/{$workcenter->id}/{$shift->id}")->assertRedirect();

        $this->assertSame(0, $workcenter->shifts()->count());
        $this->assertSame(0, WorkcenterShiftCapacity::query()->where('workcenter_id', $workcenter->id)->count());
        $this->assertSame(0, WorkcenterShiftDateOverride::query()->where('workcenter_id', $workcenter->id)->count());
    }

    public function test_destroy_404s_on_an_unassigned_pair(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->delete("/schedule/{$workcenter->id}/{$shift->id}")->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function validPayload(Workcenter $workcenter, Shift $shift, array $spots = [0, 0, 0, 0, 0, 0, 0]): array
    {
        return [
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'spots' => $spots,
        ];
    }
}
