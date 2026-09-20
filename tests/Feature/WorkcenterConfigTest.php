<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkcenterConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_write_a_workcenter(): void
    {
        $this->post('/settings/workcenters', $this->validPayload())->assertRedirect('/login');
        $this->assertSame(0, Workcenter::count());
    }

    public function test_manager_cannot_write_a_workcenter(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/settings/workcenters', $this->validPayload())->assertForbidden();
        $this->assertSame(0, Workcenter::count());
    }

    // ── Index payload ───────────────────────────────────────────────────

    public function test_settings_lists_workcenters_in_position_order_with_archived_last(): void
    {
        $this->actingAsAdmin();

        $second = Workcenter::factory()->create(['name' => 'Line 2', 'position' => 2]);
        $first = Workcenter::factory()->create(['name' => 'Line 1', 'position' => 1]);
        $archived = Workcenter::factory()->create(['name' => 'Line 0', 'position' => 0, 'archived_at' => now()]);

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('workcenters', 3)
                ->where('workcenters.0.name', $first->name)
                ->where('workcenters.1.name', $second->name)
                ->where('workcenters.2.name', $archived->name)
                ->whereNot('workcenters.2.archived_at', null)
            );
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_admin_adds_a_workcenter_at_the_end(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['position' => 5]);

        $this->post('/settings/workcenters', $this->validPayload(['name' => 'Assembly']))
            ->assertRedirect();

        $this->assertDatabaseHas('workcenters', ['name' => 'Assembly', 'position' => 6]);
    }

    public function test_the_first_workcenter_takes_position_one(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/workcenters', $this->validPayload(['name' => 'Assembly']));

        $this->assertDatabaseHas('workcenters', ['name' => 'Assembly', 'position' => 1]);
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/workcenters', $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
        $this->assertSame(0, Workcenter::count());
    }

    public function test_an_over_long_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/workcenters', $this->validPayload(['name' => str_repeat('a', 51)]))
            ->assertSessionHasErrors('name');
    }

    public function test_a_duplicate_name_is_rejected_regardless_of_case(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['name' => 'Assembly']);

        $this->post('/settings/workcenters', $this->validPayload(['name' => 'assembly']))
            ->assertSessionHasErrors('name');
        $this->assertSame(1, Workcenter::count());
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function test_admin_updates_a_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['name' => 'Assembly']);

        $this->put("/settings/workcenters/{$workcenter->id}", [
            'name' => 'Assembly Line',
        ])->assertRedirect();

        $this->assertDatabaseHas('workcenters', ['id' => $workcenter->id, 'name' => 'Assembly Line']);
    }

    public function test_updating_to_another_workcenters_name_is_rejected(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['name' => 'Assembly']);
        $workcenter = Workcenter::factory()->create(['name' => 'Packaging']);

        $this->put("/settings/workcenters/{$workcenter->id}", $this->validPayload(['name' => 'assembly']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('workcenters', ['id' => $workcenter->id, 'name' => 'Packaging']);
    }

    public function test_setting_archived_true_sets_archived_at(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['archived_at' => null]);

        $this->put("/settings/workcenters/{$workcenter->id}", $this->validPayload(['archived' => true]))
            ->assertRedirect();

        $this->assertNotNull($workcenter->fresh()->archived_at);
    }

    public function test_setting_archived_false_clears_archived_at(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['archived_at' => now()]);

        $this->put("/settings/workcenters/{$workcenter->id}", $this->validPayload(['archived' => false]))
            ->assertRedirect();

        $this->assertNull($workcenter->fresh()->archived_at);
    }

    // ── Responsible ────────────────────────────────────────────────────

    public function test_admin_adds_a_workcenter_with_a_responsible_name(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/workcenters', $this->validPayload(['responsible' => 'Jane Doe']))
            ->assertRedirect();

        $this->assertDatabaseHas('workcenters', ['name' => 'Assembly', 'responsible' => 'Jane Doe']);
    }

    public function test_the_responsible_name_is_optional(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/workcenters', $this->validPayload())->assertRedirect();

        $this->assertDatabaseHas('workcenters', ['name' => 'Assembly', 'responsible' => null]);
    }

    public function test_admin_updates_and_clears_the_responsible_name(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create(['responsible' => 'Jane Doe']);

        $this->put("/settings/workcenters/{$workcenter->id}", [
            'name' => $workcenter->name,
            'responsible' => 'John Smith',
        ])->assertRedirect();
        $this->assertSame('John Smith', $workcenter->fresh()->responsible);

        $this->put("/settings/workcenters/{$workcenter->id}", [
            'name' => $workcenter->name,
            'responsible' => '',
        ])->assertRedirect();
        $this->assertNull($workcenter->fresh()->responsible);
    }

    public function test_an_over_long_responsible_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/workcenters', $this->validPayload(['responsible' => str_repeat('a', 51)]))
            ->assertSessionHasErrors('responsible');
        $this->assertSame(0, Workcenter::count());
    }

    public function test_settings_payload_includes_the_responsible_name(): void
    {
        $this->actingAsAdmin();
        Workcenter::factory()->create(['responsible' => 'Jane Doe']);

        $this->get('/settings')->assertInertia(fn ($page) => $page
            ->where('workcenters.0.responsible', 'Jane Doe'));
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function test_deleting_an_empty_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();

        $this->delete("/settings/workcenters/{$workcenter->id}")->assertRedirect();

        $this->assertDatabaseMissing('workcenters', ['id' => $workcenter->id]);
    }

    public function test_deleting_a_workcenter_with_an_attached_shift_is_rejected(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $workcenter->shifts()->attach(Shift::factory()->create());

        $this->delete("/settings/workcenters/{$workcenter->id}")
            ->assertStatus(302)
            ->assertSessionHasErrors('workcenter');

        $this->assertDatabaseHas('workcenters', ['id' => $workcenter->id]);
    }

    // ── Reorder ────────────────────────────────────────────────────────

    public function test_reorder_sets_every_rows_position_from_the_given_order(): void
    {
        $this->actingAsAdmin();
        $a = Workcenter::factory()->create(['position' => 1]);
        $b = Workcenter::factory()->create(['position' => 2]);
        $c = Workcenter::factory()->create(['position' => 3]);

        $this->put('/settings/workcenters/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_reorder_rejects_a_partial_id_set(): void
    {
        $this->actingAsAdmin();
        $a = Workcenter::factory()->create(['position' => 1]);
        Workcenter::factory()->create(['position' => 2]);

        $this->put('/settings/workcenters/reorder', ['ids' => [$a->id]])
            ->assertSessionHasErrors('ids');
    }

    public function test_reorder_rejects_an_id_that_does_not_belong(): void
    {
        $this->actingAsAdmin();
        $a = Workcenter::factory()->create(['position' => 1]);
        $b = Workcenter::factory()->create(['position' => 2]);

        $this->put('/settings/workcenters/reorder', ['ids' => [$a->id, $b->id + 999]])
            ->assertSessionHasErrors('ids');
    }

    public function test_guest_cannot_reorder_workcenters(): void
    {
        $a = Workcenter::factory()->create(['position' => 1]);
        $b = Workcenter::factory()->create(['position' => 2]);

        $this->put('/settings/workcenters/reorder', ['ids' => [$b->id, $a->id]])
            ->assertRedirect('/login');

        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_manager_cannot_reorder_workcenters(): void
    {
        $this->actingAs(User::factory()->create());
        $a = Workcenter::factory()->create(['position' => 1]);
        $b = Workcenter::factory()->create(['position' => 2]);

        $this->put('/settings/workcenters/reorder', ['ids' => [$b->id, $a->id]])
            ->assertForbidden();

        $this->assertSame(1, $a->fresh()->position);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Assembly',
        ], $overrides);
    }
}
