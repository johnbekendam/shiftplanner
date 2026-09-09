<?php

namespace Tests\Feature;

use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionConfigTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_guest_cannot_write_a_question(): void
    {
        $this->post('/settings/questions', ['name' => 'Weekend?'])->assertRedirect('/login');
        $this->assertSame(0, AvailabilityQuestion::count());
    }

    public function test_a_manager_cannot_write_a_question(): void
    {
        $this->actingAs(User::factory()->create()); // role: manager

        $this->post('/settings/questions', ['name' => 'Weekend?'])->assertForbidden();
        $this->assertSame(0, AvailabilityQuestion::count());
    }

    // ── Index payload ────────────────────────────────────────────────────

    public function test_settings_lists_questions_in_position_order_with_holder_count(): void
    {
        $this->actingAsAdmin();

        $second = AvailabilityQuestion::factory()->create(['text' => 'Week 53?', 'position' => 2]);
        $first = AvailabilityQuestion::factory()->create(['text' => 'Weekend?', 'position' => 1]);

        $first->employees()->attach(Employee::factory()->count(3)->create());
        $second->employees()->attach(Employee::factory()->create());

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->has('questions', 2)
                ->where('questions.0.name', 'Weekend?')
                ->where('questions.0.holder_count', 3)
                ->where('questions.1.name', 'Week 53?')
                ->where('questions.1.holder_count', 1)
            );
    }

    // ── Create ──────────────────────────────────────────────────────────

    public function test_manager_adds_a_question_at_the_end(): void
    {
        $this->actingAsAdmin();
        AvailabilityQuestion::factory()->create(['text' => 'Weekend?', 'position' => 5]);

        $this->post('/settings/questions', ['name' => 'Week 53?'])->assertRedirect();

        $this->assertDatabaseHas('availability_questions', ['text' => 'Week 53?', 'position' => 6]);
    }

    public function test_the_first_question_takes_position_one(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/questions', ['name' => 'Weekend?']);

        $this->assertDatabaseHas('availability_questions', ['text' => 'Weekend?', 'position' => 1]);
    }

    public function test_a_blank_question_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/questions', ['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(0, AvailabilityQuestion::count());
    }

    public function test_an_over_long_question_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/settings/questions', ['name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, AvailabilityQuestion::count());
    }

    public function test_a_duplicate_question_is_rejected_regardless_of_case(): void
    {
        $this->actingAsAdmin();
        AvailabilityQuestion::factory()->create(['text' => 'Weekend?']);

        $this->post('/settings/questions', ['name' => 'WEEKEND?'])->assertSessionHasErrors('name');
        $this->assertSame(1, AvailabilityQuestion::count());
    }

    // ── Rename ──────────────────────────────────────────────────────────

    public function test_manager_renames_a_question(): void
    {
        $this->actingAsAdmin();
        $question = AvailabilityQuestion::factory()->create(['text' => 'Weekend?']);

        $this->put("/settings/questions/{$question->id}", ['name' => 'Can we call you in for a weekend?'])
            ->assertRedirect();

        $this->assertDatabaseHas('availability_questions', [
            'id' => $question->id,
            'text' => 'Can we call you in for a weekend?',
        ]);
    }

    public function test_renaming_to_another_questions_text_is_rejected(): void
    {
        $this->actingAsAdmin();
        AvailabilityQuestion::factory()->create(['text' => 'Weekend?']);
        $question = AvailabilityQuestion::factory()->create(['text' => 'Week 53?']);

        $this->put("/settings/questions/{$question->id}", ['name' => 'weekend?'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('availability_questions', ['id' => $question->id, 'text' => 'Week 53?']);
    }

    public function test_renaming_a_question_to_its_own_text_is_allowed(): void
    {
        $this->actingAsAdmin();
        $question = AvailabilityQuestion::factory()->create(['text' => 'Weekend?']);

        $this->put("/settings/questions/{$question->id}", ['name' => 'Weekend?'])
            ->assertSessionHasNoErrors();
    }

    // ── Delete ──────────────────────────────────────────────────────────

    public function test_deleting_a_question_removes_it_and_its_answers(): void
    {
        $this->actingAsAdmin();
        $question = AvailabilityQuestion::factory()->create();
        $employee = Employee::factory()->create();
        $question->employees()->attach($employee);

        $this->delete("/settings/questions/{$question->id}")->assertRedirect();

        $this->assertDatabaseMissing('availability_questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('availability_question_employee', ['availability_question_id' => $question->id]);
    }

    // ── Reorder ─────────────────────────────────────────────────────────

    public function test_move_down_swaps_with_the_next_row(): void
    {
        $this->actingAsAdmin();
        $a = AvailabilityQuestion::factory()->create(['text' => 'A', 'position' => 1]);
        $b = AvailabilityQuestion::factory()->create(['text' => 'B', 'position' => 2]);

        $this->put("/settings/questions/{$a->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_move_up_swaps_with_the_previous_row(): void
    {
        $this->actingAsAdmin();
        $a = AvailabilityQuestion::factory()->create(['text' => 'A', 'position' => 1]);
        $b = AvailabilityQuestion::factory()->create(['text' => 'B', 'position' => 2]);

        $this->put("/settings/questions/{$b->id}/move", ['direction' => 'up'])->assertRedirect();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(1, $b->fresh()->position);
    }

    public function test_moving_past_an_end_changes_nothing(): void
    {
        $this->actingAsAdmin();
        $a = AvailabilityQuestion::factory()->create(['text' => 'A', 'position' => 1]);
        $b = AvailabilityQuestion::factory()->create(['text' => 'B', 'position' => 2]);

        $this->put("/settings/questions/{$a->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->put("/settings/questions/{$b->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_an_unknown_move_direction_is_rejected(): void
    {
        $this->actingAsAdmin();
        $question = AvailabilityQuestion::factory()->create(['position' => 1]);

        $this->put("/settings/questions/{$question->id}/move", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }
}
