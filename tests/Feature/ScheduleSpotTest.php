<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Workcenter;
use App\Models\WorkcenterShiftDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleSpotTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function url(Workcenter $workcenter, Shift $shift, string $date = '2026-09-15'): string
    {
        return "/scheduling/spots/{$workcenter->id}/{$shift->id}/{$date}";
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_update_a_spot_count(): void
    {
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put($this->url($workcenter, $shift), ['spots' => 4])->assertRedirect('/login');
        $this->assertSame(0, WorkcenterShiftDateOverride::count());
    }

    public function test_manager_cannot_update_a_spot_count(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put($this->url($workcenter, $shift), ['spots' => 4])->assertForbidden();
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function test_update_creates_an_override(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->put($this->url($workcenter, $shift), ['spots' => 4])->assertRedirect();

        $this->assertDatabaseHas('workcenter_shift_date_overrides', [
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-15',
            'spots' => 4,
        ]);
    }

    public function test_update_on_an_already_overridden_date_replaces_it(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-15', 'spots' => 2,
        ]);

        $this->put($this->url($workcenter, $shift), ['spots' => 5])->assertRedirect();

        $this->assertSame(1, WorkcenterShiftDateOverride::count());
        $this->assertDatabaseHas('workcenter_shift_date_overrides', [
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-15', 'spots' => 5,
        ]);
    }

    public function test_update_rejects_a_value_below_the_assignee_count(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        ShiftAssignment::factory()->count(2)->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-15',
        ]);

        $this->put($this->url($workcenter, $shift), ['spots' => 1])->assertSessionHasErrors('spots');
        $this->assertSame(0, WorkcenterShiftDateOverride::count());
    }

    public function test_update_allows_a_value_equal_to_the_assignee_count(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        ShiftAssignment::factory()->count(2)->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-15',
        ]);

        $this->put($this->url($workcenter, $shift), ['spots' => 2])->assertSessionHasNoErrors();
    }

    // ── Delete (reset to default) ─────────────────────────────────────

    public function test_destroy_removes_an_override(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        WorkcenterShiftDateOverride::query()->create([
            'workcenter_id' => $workcenter->id, 'shift_id' => $shift->id, 'date' => '2026-09-15', 'spots' => 2,
        ]);

        $this->delete($this->url($workcenter, $shift))->assertRedirect();

        $this->assertSame(0, WorkcenterShiftDateOverride::count());
    }

    public function test_destroy_is_a_no_op_with_no_override(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->delete($this->url($workcenter, $shift))->assertRedirect();

        $this->assertSame(0, WorkcenterShiftDateOverride::count());
    }
}
