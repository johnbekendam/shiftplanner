<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_write_a_shift(): void
    {
        $this->post('/settings/shifts', $this->validPayload())->assertRedirect('/login');
        $this->assertSame(0, Shift::count());
    }

    public function test_manager_cannot_write_a_shift(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/settings/shifts', $this->validPayload())->assertForbidden();
        $this->assertSame(0, Shift::count());
    }

    // ── Index payload ───────────────────────────────────────────────────

    public function test_settings_lists_shifts_in_start_time_order(): void
    {
        $this->actingAsAdmin();

        Shift::factory()->create(['name' => 'Late', 'start_time' => '14:00', 'end_time' => '22:00']);
        Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('shifts', 2)
                ->where('shifts.0.name', 'Early')
                ->where('shifts.0.start_time', '06:00')
                ->where('shifts.0.end_time', '14:00')
                ->where('shifts.1.name', 'Late')
            );
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_admin_adds_a_shift(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/shifts', $this->validPayload(['name' => 'Night']))
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', ['name' => 'Night']);
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/shifts', $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
        $this->assertSame(0, Shift::count());
    }

    public function test_an_over_long_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/shifts', $this->validPayload(['name' => str_repeat('a', 51)]))
            ->assertSessionHasErrors('name');
    }

    public function test_a_duplicate_name_is_rejected_regardless_of_case(): void
    {
        $this->actingAsAdmin();
        Shift::factory()->create(['name' => 'Early']);

        $this->post('/settings/shifts', $this->validPayload(['name' => 'early']))
            ->assertSessionHasErrors('name');
        $this->assertSame(1, Shift::count());
    }

    public function test_a_missing_time_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/shifts', $this->validPayload(['start_time' => '']))
            ->assertSessionHasErrors('start_time');
    }

    public function test_a_malformed_time_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/shifts', $this->validPayload(['end_time' => '25:99']))
            ->assertSessionHasErrors('end_time');
    }

    public function test_an_end_time_at_or_before_the_start_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/shifts', $this->validPayload(['start_time' => '14:00', 'end_time' => '14:00']))
            ->assertSessionHasErrors('end_time');

        $this->post('/settings/shifts', $this->validPayload(['start_time' => '14:00', 'end_time' => '08:00']))
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, Shift::count());
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function test_admin_updates_a_shift(): void
    {
        $this->actingAsAdmin();
        $shift = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);

        $this->put("/settings/shifts/{$shift->id}", [
            'name' => 'Early bird',
            'start_time' => '05:30',
            'end_time' => '13:30',
        ])->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'name' => 'Early bird',
        ]);
        $this->assertSame('05:30', $shift->fresh()->start_time);
    }

    public function test_updating_to_another_shifts_name_is_rejected(): void
    {
        $this->actingAsAdmin();
        Shift::factory()->create(['name' => 'Early']);
        $shift = Shift::factory()->create(['name' => 'Late']);

        $this->put("/settings/shifts/{$shift->id}", $this->validPayload(['name' => 'early']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Late']);
    }

    public function test_updating_a_shift_keeping_its_own_name_is_allowed(): void
    {
        $this->actingAsAdmin();
        $shift = Shift::factory()->create(['name' => 'Early']);

        $this->put("/settings/shifts/{$shift->id}", $this->validPayload(['name' => 'Early']))
            ->assertSessionHasNoErrors();
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function test_admin_deletes_a_shift(): void
    {
        $this->actingAsAdmin();
        $shift = Shift::factory()->create();

        $this->delete("/settings/shifts/{$shift->id}")->assertRedirect();

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Early',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ], $overrides);
    }
}
