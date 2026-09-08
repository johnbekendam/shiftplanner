<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeProductGroupTest extends TestCase
{
    use RefreshDatabase;

    private function token(Employee $employee): string
    {
        return $employee->personalLink()->create(['token' => 'tok-'.$employee->id])->token;
    }

    // ── Manager route ────────────────────────────────────────────────────

    public function test_guest_cannot_toggle_a_product_group(): void
    {
        $employee = Employee::factory()->create();
        $group = ProductGroup::factory()->create();

        $this->put("/employees/{$employee->id}/product-groups/{$group->id}")
            ->assertRedirect('/login');

        $this->assertDatabaseCount('employee_product_group', 0);
    }

    public function test_manager_attaches_then_detaches_a_product_group(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $group = ProductGroup::factory()->create();
        $base = "/employees/{$employee->id}/product-groups/{$group->id}";

        $this->actingAs($user)->put($base)->assertRedirect();
        $this->assertDatabaseHas('employee_product_group', [
            'employee_id' => $employee->id,
            'product_group_id' => $group->id,
        ]);

        $this->actingAs($user)->delete($base)->assertRedirect();
        $this->assertDatabaseCount('employee_product_group', 0);
    }

    public function test_attaching_twice_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $group = ProductGroup::factory()->create();
        $base = "/employees/{$employee->id}/product-groups/{$group->id}";

        $this->actingAs($user)->put($base);
        $this->actingAs($user)->put($base);

        $this->assertDatabaseCount('employee_product_group', 1);
    }

    public function test_detaching_a_group_the_employee_does_not_prefer_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $group = ProductGroup::factory()->create();

        $this->actingAs($user)
            ->delete("/employees/{$employee->id}/product-groups/{$group->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('employee_product_group', 0);
    }

    public function test_an_unknown_product_group_is_not_found(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->put("/employees/{$employee->id}/product-groups/9999")->assertNotFound();
    }

    public function test_edit_payload_lists_all_product_groups_and_the_preferred_ids(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $b = ProductGroup::factory()->create(['name' => 'B', 'position' => 2]);
        $a = ProductGroup::factory()->create(['name' => 'A', 'position' => 1]);
        $employee->productGroups()->attach($b);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('productGroups', 2)
                ->where('productGroups.0.name', 'A')
                ->where('productGroups.1.name', 'B')
                ->where('productGroupIds', [$b->id])
            );
    }

    // ── Personal route ──────────────────────────────────────────────────

    public function test_employee_toggles_a_product_group_by_token(): void
    {
        $employee = Employee::factory()->create();
        $group = ProductGroup::factory()->create();
        $token = $this->token($employee);
        $base = "/personal/{$token}/product-groups/{$group->id}";

        $this->put($base)->assertRedirect("/personal/{$token}");
        $this->assertDatabaseHas('employee_product_group', [
            'employee_id' => $employee->id,
            'product_group_id' => $group->id,
        ]);

        $this->delete($base)->assertRedirect("/personal/{$token}");
        $this->assertDatabaseCount('employee_product_group', 0);
    }

    public function test_a_bad_token_is_404(): void
    {
        $group = ProductGroup::factory()->create();

        $this->put("/personal/not-a-token/product-groups/{$group->id}")->assertNotFound();
        $this->assertDatabaseCount('employee_product_group', 0);
    }

    public function test_show_payload_lists_all_product_groups_and_the_preferred_ids(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);
        $a = ProductGroup::factory()->create(['name' => 'A', 'position' => 1]);
        ProductGroup::factory()->create(['name' => 'B', 'position' => 2]);
        $employee->productGroups()->attach($a);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->has('productGroups', 2)
                ->where('productGroups.0.name', 'A')
                ->where('productGroupIds', [$a->id])
            );
    }
}
