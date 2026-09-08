<?php

namespace Tests\Feature;

use App\Models\Competence;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetenceConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Guests ────────────────────────────────────────────────────────────

    public function test_guest_cannot_open_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_guest_cannot_write_a_competence(): void
    {
        $this->post('/settings/competences', ['name' => 'Forklift'])->assertRedirect('/login');
        $this->assertSame(0, Competence::count());
    }

    // ── Index payload ────────────────────────────────────────────────────

    public function test_settings_lists_competences_in_position_order_with_holder_count(): void
    {
        $this->actingAsAdmin();

        $second = Competence::factory()->create(['name' => 'Cleanroom', 'position' => 2]);
        $first = Competence::factory()->create(['name' => 'Forklift', 'position' => 1]);

        $first->employees()->attach(Employee::factory()->count(3)->create());
        $second->employees()->attach(Employee::factory()->create());

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('competences', 2)
                ->where('competences.0.name', 'Forklift')
                ->where('competences.0.holder_count', 3)
                ->where('competences.1.name', 'Cleanroom')
                ->where('competences.1.holder_count', 1)
            );
    }

    // ── Create ──────────────────────────────────────────────────────────

    public function test_manager_adds_a_competence_at_the_end(): void
    {
        $this->actingAsAdmin();
        Competence::factory()->create(['name' => 'Forklift', 'position' => 5]);

        $this->post('/settings/competences', ['name' => 'First aid'])->assertRedirect();

        $this->assertDatabaseHas('competences', ['name' => 'First aid', 'position' => 6]);
    }

    public function test_the_first_competence_takes_position_one(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/competences', ['name' => 'First aid']);

        $this->assertDatabaseHas('competences', ['name' => 'First aid', 'position' => 1]);
    }

    public function test_a_blank_name_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/competences', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(0, Competence::count());
    }

    public function test_a_duplicate_name_is_rejected_regardless_of_case(): void
    {
        $this->actingAsAdmin();
        Competence::factory()->create(['name' => 'Forklift']);

        $this->post('/settings/competences', ['name' => 'FORKLIFT'])->assertSessionHasErrors('name');
        $this->assertSame(1, Competence::count());
    }

    // ── Rename ──────────────────────────────────────────────────────────

    public function test_manager_renames_a_competence(): void
    {
        $this->actingAsAdmin();
        $competence = Competence::factory()->create(['name' => 'Forklift']);

        $this->put("/settings/competences/{$competence->id}", ['name' => 'Forklift licence'])
            ->assertRedirect();

        $this->assertDatabaseHas('competences', ['id' => $competence->id, 'name' => 'Forklift licence']);
    }

    public function test_renaming_to_another_competences_name_is_rejected(): void
    {
        $this->actingAsAdmin();
        Competence::factory()->create(['name' => 'Forklift']);
        $competence = Competence::factory()->create(['name' => 'Cleanroom']);

        $this->put("/settings/competences/{$competence->id}", ['name' => 'forklift'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('competences', ['id' => $competence->id, 'name' => 'Cleanroom']);
    }

    public function test_renaming_a_competence_to_its_own_name_is_allowed(): void
    {
        $this->actingAsAdmin();
        $competence = Competence::factory()->create(['name' => 'Forklift']);

        $this->put("/settings/competences/{$competence->id}", ['name' => 'Forklift'])
            ->assertSessionHasNoErrors();
    }

    // ── Delete ──────────────────────────────────────────────────────────

    public function test_deleting_a_competence_removes_it_and_its_links(): void
    {
        $this->actingAsAdmin();
        $competence = Competence::factory()->create();
        $employee = Employee::factory()->create();
        $competence->employees()->attach($employee);

        $this->delete("/settings/competences/{$competence->id}")->assertRedirect();

        $this->assertDatabaseMissing('competences', ['id' => $competence->id]);
        $this->assertDatabaseMissing('competence_employee', ['competence_id' => $competence->id]);
    }

    // ── Reorder ─────────────────────────────────────────────────────────

    public function test_move_down_swaps_with_the_next_row(): void
    {
        $this->actingAsAdmin();
        $a = Competence::factory()->create(['name' => 'A', 'position' => 1]);
        $b = Competence::factory()->create(['name' => 'B', 'position' => 2]);

        $this->put("/settings/competences/{$a->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_move_up_swaps_with_the_previous_row(): void
    {
        $this->actingAsAdmin();
        $a = Competence::factory()->create(['name' => 'A', 'position' => 1]);
        $b = Competence::factory()->create(['name' => 'B', 'position' => 2]);

        $this->put("/settings/competences/{$b->id}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_moving_past_an_end_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $a = Competence::factory()->create(['name' => 'A', 'position' => 1]);
        $b = Competence::factory()->create(['name' => 'B', 'position' => 2]);

        $this->put("/settings/competences/{$a->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->put("/settings/competences/{$b->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_an_unknown_move_direction_is_rejected(): void
    {
        $this->actingAsAdmin();
        $competence = Competence::factory()->create(['position' => 1]);

        $this->put("/settings/competences/{$competence->id}/move", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }
}
