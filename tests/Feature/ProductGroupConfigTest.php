<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGroupConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Guests ────────────────────────────────────────────────────────────

    public function test_guest_cannot_write_a_product_group(): void
    {
        $this->post('/settings/product-groups', ['name' => 'Pumps'])->assertRedirect('/login');
        $this->assertSame(0, ProductGroup::count());
    }

    // ── Index payload ────────────────────────────────────────────────────

    public function test_settings_lists_product_groups_in_position_order_with_holder_count(): void
    {
        $this->actingAsAdmin();

        $second = ProductGroup::factory()->create(['name' => 'Valves', 'position' => 2]);
        $first = ProductGroup::factory()->create(['name' => 'Pumps', 'position' => 1]);

        $first->employees()->attach(Employee::factory()->count(3)->create());
        $second->employees()->attach(Employee::factory()->create());

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('productGroups', 2)
                ->where('productGroups.0.name', 'Pumps')
                ->where('productGroups.0.holder_count', 3)
                ->where('productGroups.1.name', 'Valves')
                ->where('productGroups.1.holder_count', 1)
            );
    }

    // ── Create ──────────────────────────────────────────────────────────

    public function test_manager_adds_a_product_group_at_the_end(): void
    {
        $this->actingAsAdmin();
        ProductGroup::factory()->create(['name' => 'Pumps', 'position' => 5]);

        $this->post('/settings/product-groups', ['name' => 'Sensors'])->assertRedirect();

        $this->assertDatabaseHas('product_groups', ['name' => 'Sensors', 'position' => 6]);
    }

    public function test_the_first_product_group_takes_position_one(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/product-groups', ['name' => 'Sensors']);

        $this->assertDatabaseHas('product_groups', ['name' => 'Sensors', 'position' => 1]);
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/product-groups', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(0, ProductGroup::count());
    }

    public function test_a_duplicate_name_is_rejected_regardless_of_case(): void
    {
        $this->actingAsAdmin();
        ProductGroup::factory()->create(['name' => 'Pumps']);

        $this->post('/settings/product-groups', ['name' => 'PUMPS'])->assertSessionHasErrors('name');
        $this->assertSame(1, ProductGroup::count());
    }

    // ── Rename ──────────────────────────────────────────────────────────

    public function test_manager_renames_a_product_group(): void
    {
        $this->actingAsAdmin();
        $group = ProductGroup::factory()->create(['name' => 'Pumps']);

        $this->put("/settings/product-groups/{$group->id}", ['name' => 'Pump systems'])
            ->assertRedirect();

        $this->assertDatabaseHas('product_groups', ['id' => $group->id, 'name' => 'Pump systems']);
    }

    public function test_renaming_to_another_groups_name_is_rejected(): void
    {
        $this->actingAsAdmin();
        ProductGroup::factory()->create(['name' => 'Pumps']);
        $group = ProductGroup::factory()->create(['name' => 'Valves']);

        $this->put("/settings/product-groups/{$group->id}", ['name' => 'pumps'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('product_groups', ['id' => $group->id, 'name' => 'Valves']);
    }

    public function test_renaming_a_product_group_to_its_own_name_is_allowed(): void
    {
        $this->actingAsAdmin();
        $group = ProductGroup::factory()->create(['name' => 'Pumps']);

        $this->put("/settings/product-groups/{$group->id}", ['name' => 'Pumps'])
            ->assertSessionHasNoErrors();
    }

    // ── Delete ──────────────────────────────────────────────────────────

    public function test_deleting_a_product_group_removes_it_and_its_links(): void
    {
        $this->actingAsAdmin();
        $group = ProductGroup::factory()->create();
        $employee = Employee::factory()->create();
        $group->employees()->attach($employee);

        $this->delete("/settings/product-groups/{$group->id}")->assertRedirect();

        $this->assertDatabaseMissing('product_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('employee_product_group', ['product_group_id' => $group->id]);
    }

    // ── Reorder ─────────────────────────────────────────────────────────

    public function test_move_down_swaps_with_the_next_row(): void
    {
        $this->actingAsAdmin();
        $a = ProductGroup::factory()->create(['name' => 'A', 'position' => 1]);
        $b = ProductGroup::factory()->create(['name' => 'B', 'position' => 2]);

        $this->put("/settings/product-groups/{$a->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_move_up_swaps_with_the_previous_row(): void
    {
        $this->actingAsAdmin();
        $a = ProductGroup::factory()->create(['name' => 'A', 'position' => 1]);
        $b = ProductGroup::factory()->create(['name' => 'B', 'position' => 2]);

        $this->put("/settings/product-groups/{$b->id}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_moving_past_an_end_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $a = ProductGroup::factory()->create(['name' => 'A', 'position' => 1]);
        $b = ProductGroup::factory()->create(['name' => 'B', 'position' => 2]);

        $this->put("/settings/product-groups/{$a->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->put("/settings/product-groups/{$b->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_an_unknown_move_direction_is_rejected(): void
    {
        $this->actingAsAdmin();
        $group = ProductGroup::factory()->create(['position' => 1]);

        $this->put("/settings/product-groups/{$group->id}/move", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }
}
