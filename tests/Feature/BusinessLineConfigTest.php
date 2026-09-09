<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLineConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_write_a_business_line(): void
    {
        $this->post('/settings/business-lines', $this->validPayload())->assertRedirect('/login');
        $this->assertSame(0, BusinessLine::count());
    }

    public function test_manager_cannot_write_a_business_line(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/settings/business-lines', $this->validPayload())->assertForbidden();
        $this->assertSame(0, BusinessLine::count());
    }

    // ── Index payload ───────────────────────────────────────────────────

    public function test_settings_lists_business_lines_in_position_order_with_employee_count(): void
    {
        $this->actingAsAdmin();

        $second = BusinessLine::factory()->create(['abbreviation' => 'VLV', 'position' => 2]);
        $first = BusinessLine::factory()->create(['abbreviation' => 'PMP', 'position' => 1, 'target_fte' => 12.5]);

        Employee::factory()->count(3)->create(['business_line_id' => $first->id]);
        Employee::factory()->create(['business_line_id' => $second->id]);

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('businessLines', 2)
                ->where('businessLines.0.abbreviation', 'PMP')
                ->where('businessLines.0.target_fte', 12.5)
                ->where('businessLines.0.employee_count', 3)
                ->where('businessLines.1.abbreviation', 'VLV')
                ->where('businessLines.1.employee_count', 1)
            );
    }

    // ── Create ─────────────────────────────────────────────────────────

    public function test_admin_adds_a_business_line_at_the_end(): void
    {
        $this->actingAsAdmin();
        BusinessLine::factory()->create(['position' => 5]);

        $this->post('/settings/business-lines', $this->validPayload(['abbreviation' => 'SNS']))
            ->assertRedirect();

        $this->assertDatabaseHas('business_lines', ['abbreviation' => 'SNS', 'position' => 6]);
    }

    public function test_the_first_business_line_takes_position_one(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/business-lines', $this->validPayload(['abbreviation' => 'SNS']));

        $this->assertDatabaseHas('business_lines', ['abbreviation' => 'SNS', 'position' => 1]);
    }

    public function test_a_blank_abbreviation_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/business-lines', $this->validPayload(['abbreviation' => '']))
            ->assertSessionHasErrors('abbreviation');
        $this->assertSame(0, BusinessLine::count());
    }

    public function test_an_over_long_abbreviation_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/business-lines', $this->validPayload(['abbreviation' => 'ABCDEFGHIJK']))
            ->assertSessionHasErrors('abbreviation');
    }

    public function test_a_duplicate_abbreviation_is_rejected_regardless_of_case(): void
    {
        $this->actingAsAdmin();
        BusinessLine::factory()->create(['abbreviation' => 'PMP']);

        $this->post('/settings/business-lines', $this->validPayload(['abbreviation' => 'pmp']))
            ->assertSessionHasErrors('abbreviation');
        $this->assertSame(1, BusinessLine::count());
    }

    public function test_a_blank_description_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/business-lines', $this->validPayload(['description' => '']))
            ->assertSessionHasErrors('description');
    }

    public function test_a_negative_target_fte_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/business-lines', $this->validPayload(['target_fte' => -1]))
            ->assertSessionHasErrors('target_fte');
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function test_admin_updates_a_business_line(): void
    {
        $this->actingAsAdmin();
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP', 'description' => 'Pumps', 'target_fte' => 4]);

        $this->put("/settings/business-lines/{$line->id}", [
            'abbreviation' => 'PMP',
            'description' => 'Pump systems',
            'target_fte' => 6.5,
        ])->assertRedirect();

        $this->assertDatabaseHas('business_lines', [
            'id' => $line->id,
            'description' => 'Pump systems',
            'target_fte' => 6.5,
        ]);
    }

    public function test_updating_to_another_lines_abbreviation_is_rejected(): void
    {
        $this->actingAsAdmin();
        BusinessLine::factory()->create(['abbreviation' => 'PMP']);
        $line = BusinessLine::factory()->create(['abbreviation' => 'VLV']);

        $this->put("/settings/business-lines/{$line->id}", $this->validPayload(['abbreviation' => 'pmp']))
            ->assertSessionHasErrors('abbreviation');

        $this->assertDatabaseHas('business_lines', ['id' => $line->id, 'abbreviation' => 'VLV']);
    }

    public function test_updating_a_line_keeping_its_own_abbreviation_is_allowed(): void
    {
        $this->actingAsAdmin();
        $line = BusinessLine::factory()->create(['abbreviation' => 'PMP']);

        $this->put("/settings/business-lines/{$line->id}", $this->validPayload(['abbreviation' => 'PMP']))
            ->assertSessionHasNoErrors();
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function test_deleting_a_business_line_nulls_its_members(): void
    {
        $this->actingAsAdmin();
        $line = BusinessLine::factory()->create();
        $employee = Employee::factory()->create(['business_line_id' => $line->id]);

        $this->delete("/settings/business-lines/{$line->id}")->assertRedirect();

        $this->assertDatabaseMissing('business_lines', ['id' => $line->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'business_line_id' => null]);
    }

    // ── Reorder ────────────────────────────────────────────────────────

    public function test_move_down_swaps_with_the_next_row(): void
    {
        $this->actingAsAdmin();
        $a = BusinessLine::factory()->create(['position' => 1]);
        $b = BusinessLine::factory()->create(['position' => 2]);

        $this->put("/settings/business-lines/{$a->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_move_up_swaps_with_the_previous_row(): void
    {
        $this->actingAsAdmin();
        $a = BusinessLine::factory()->create(['position' => 1]);
        $b = BusinessLine::factory()->create(['position' => 2]);

        $this->put("/settings/business-lines/{$b->id}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_moving_past_an_end_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $a = BusinessLine::factory()->create(['position' => 1]);
        $b = BusinessLine::factory()->create(['position' => 2]);

        $this->put("/settings/business-lines/{$a->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->put("/settings/business-lines/{$b->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_an_unknown_move_direction_is_rejected(): void
    {
        $this->actingAsAdmin();
        $line = BusinessLine::factory()->create(['position' => 1]);

        $this->put("/settings/business-lines/{$line->id}/move", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'abbreviation' => 'PMP',
            'description' => 'Pumps',
            'target_fte' => 4.0,
        ], $overrides);
    }
}
