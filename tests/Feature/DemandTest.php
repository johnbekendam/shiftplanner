<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftCapacity;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandTest extends TestCase
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
        $this->get('/demand')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden_from_the_index(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/demand')->assertForbidden();
    }

    public function test_guest_cannot_create_an_assignment(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/demand', $this->validPayload($workcenter, $shift))->assertRedirect('/login');
        $this->assertSame(0, $workcenter->shifts()->count());
    }

    public function test_manager_cannot_create_an_assignment(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/demand', $this->validPayload($workcenter, $shift))->assertForbidden();
    }

    // ── Index payload ───────────────────────────────────────────────────

    public function test_index_selects_the_first_active_workcenter_without_a_query(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['name' => 'Line 0', 'position' => 0, 'archived_at' => now()]);
        $first = Workcenter::factory()->create(['name' => 'Line 1', 'position' => 1]);
        Workcenter::factory()->create(['name' => 'Line 2', 'position' => 2]);
        Shift::factory()->create(['name' => 'Early']);

        $this->get('/demand')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Demand')
                ->has('workcenters', 2)
                ->where('workcenters.0.name', 'Line 1')
                ->where('workcenterId', $first->id)
                ->has('shifts', 1)
            );
    }

    public function test_index_sends_the_demand_of_the_selected_workcenter_only(): void
    {
        $this->actingAsAdmin();
        $other = Workcenter::factory()->create(['position' => 0]);
        $selected = Workcenter::factory()->create(['position' => 1]);
        $shift = Shift::factory()->create();
        foreach ([$other, $selected] as $workcenter) {
            $workcenter->shifts()->attach($shift);
            WorkcenterShiftCapacity::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'weekday' => 1, 'spots' => $workcenter->id,
            ]);
            WorkcenterShiftDateOverride::query()->create([
                'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-12-24', 'spots' => 5,
            ]);
        }
        ShiftAssignment::factory()->count(2)->create([
            'workcenter_id' => $selected->id, 'shift_id' => $shift->id, 'date' => '2026-12-24',
        ]);
        ShiftAssignment::factory()->create([
            'workcenter_id' => $other->id, 'shift_id' => $shift->id, 'date' => '2026-12-24',
        ]);

        $this->get("/demand?workcenter={$selected->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workcenterId', $selected->id)
                ->where('defaults', [['shift_id' => $shift->id, 'spots' => [$selected->id, 0, 0, 0, 0, 0, 0]]])
                ->where('overrides', [['shift_id' => $shift->id, 'date' => '2026-12-24', 'spots' => 5]])
                ->where('assigned', [['shift_id' => $shift->id, 'date' => '2026-12-24', 'count' => 2]])
            );
    }

    public function test_index_falls_back_to_the_first_active_workcenter_for_an_archived_or_unknown_one(): void
    {
        $this->actingAsAdmin();
        $first = Workcenter::factory()->create(['position' => 1]);
        $archived = Workcenter::factory()->create(['position' => 2, 'archived_at' => now()]);

        $this->get("/demand?workcenter={$archived->id}")
            ->assertInertia(fn ($page) => $page->where('workcenterId', $first->id));
        $this->get('/demand?workcenter=999999')
            ->assertInertia(fn ($page) => $page->where('workcenterId', $first->id));
    }

    public function test_index_without_active_workcenters_selects_nothing(): void
    {
        $this->actingAsAdmin();

        $this->get('/demand')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workcenterId', null)
                ->where('defaults', [])
                ->where('overrides', [])
                ->where('assigned', [])
            );
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_store_creates_the_pivot_and_seven_capacity_rows(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/demand', $this->validPayload($workcenter, $shift, [4, 4, 4, 4, 2, 0, 0]))
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

        $this->post('/demand', $this->validPayload($workcenter, $shift))
            ->assertSessionHasErrors('shift_id');
    }

    public function test_store_rejects_an_archived_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['archived_at' => now()]);
        $shift = Shift::factory()->create();

        $this->post('/demand', $this->validPayload($workcenter, $shift))
            ->assertSessionHasErrors('workcenter_id');
    }

    public function test_store_rejects_a_missing_workcenter_or_shift(): void
    {
        $this->actingAsAdmin();
        $shift = Shift::factory()->create();

        $this->post('/demand', [
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

        $this->post('/demand', $this->validPayload($workcenter, $shift, [4, 4, 4, 4, -1, 0, 0]))
            ->assertSessionHasErrors('spots.4');
    }

    public function test_store_rejects_a_short_spots_array(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->post('/demand', [
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
        $this->put("/demand/{$workcenter->id}/{$shift->id}", ['spots' => $spots])
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

        $this->put("/demand/{$workcenter->id}/{$shift->id}", ['spots' => array_fill(0, 7, 1)])
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

        $this->delete("/demand/{$workcenter->id}/{$shift->id}")->assertRedirect();

        $this->assertSame(0, $workcenter->shifts()->count());
        $this->assertSame(0, WorkcenterShiftCapacity::query()->where('workcenter_id', $workcenter->id)->count());
        $this->assertSame(0, WorkcenterShiftDateOverride::query()->where('workcenter_id', $workcenter->id)->count());
    }

    public function test_destroy_404s_on_an_unassigned_pair(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->delete("/demand/{$workcenter->id}/{$shift->id}")->assertNotFound();
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
