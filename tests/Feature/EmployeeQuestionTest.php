<?php

namespace Tests\Feature;

use App\Models\AvailabilityQuestion;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeQuestionTest extends TestCase
{
    use RefreshDatabase;

    private function token(Employee $employee): string
    {
        return $employee->personalLink()->create(['token' => 'tok-'.$employee->id])->token;
    }

    // ── Manager route ────────────────────────────────────────────────────

    public function test_guest_cannot_answer_a_question(): void
    {
        $employee = Employee::factory()->create();
        $question = AvailabilityQuestion::factory()->create();

        $this->put("/employees/{$employee->id}/questions/{$question->id}", ['answer' => true])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('availability_question_employee', 0);
    }

    public function test_manager_answers_yes_then_no(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $question = AvailabilityQuestion::factory()->create();
        $base = "/employees/{$employee->id}/questions/{$question->id}";

        $this->actingAs($user)->put($base, ['answer' => true])->assertRedirect();
        $this->assertDatabaseHas('availability_question_employee', [
            'employee_id' => $employee->id,
            'availability_question_id' => $question->id,
        ]);

        $this->actingAs($user)->put($base, ['answer' => false])->assertRedirect();
        $this->assertDatabaseCount('availability_question_employee', 0);
    }

    public function test_answering_yes_twice_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $question = AvailabilityQuestion::factory()->create();
        $base = "/employees/{$employee->id}/questions/{$question->id}";

        $this->actingAs($user)->put($base, ['answer' => true]);
        $this->actingAs($user)->put($base, ['answer' => true]);

        $this->assertDatabaseCount('availability_question_employee', 1);
    }

    public function test_a_missing_answer_is_rejected(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $question = AvailabilityQuestion::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/questions/{$question->id}", [])
            ->assertSessionHasErrors('answer');
    }

    public function test_an_unknown_question_is_not_found(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)
            ->put("/employees/{$employee->id}/questions/9999", ['answer' => true])
            ->assertNotFound();
    }

    public function test_edit_payload_lists_questions_and_answered_ids(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $b = AvailabilityQuestion::factory()->create(['text' => 'B', 'position' => 2]);
        $a = AvailabilityQuestion::factory()->create(['text' => 'A', 'position' => 1]);
        $employee->availabilityQuestions()->attach($b);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('questions', 2)
                ->where('questions.0.text', 'A')
                ->where('questions.1.text', 'B')
                ->where('questionAnswers', [$b->id])
            );
    }

    // ── Personal route ──────────────────────────────────────────────────

    public function test_employee_answers_a_question_by_token(): void
    {
        $employee = Employee::factory()->create();
        $question = AvailabilityQuestion::factory()->create();
        $token = $this->token($employee);
        $base = "/personal/{$token}/questions/{$question->id}";

        $this->put($base, ['answer' => true])->assertRedirect("/personal/{$token}");
        $this->assertDatabaseHas('availability_question_employee', [
            'employee_id' => $employee->id,
            'availability_question_id' => $question->id,
        ]);

        $this->put($base, ['answer' => false])->assertRedirect("/personal/{$token}");
        $this->assertDatabaseCount('availability_question_employee', 0);
    }

    public function test_a_bad_token_is_404(): void
    {
        $question = AvailabilityQuestion::factory()->create();

        $this->put("/personal/not-a-token/questions/{$question->id}", ['answer' => true])->assertNotFound();
        $this->assertDatabaseCount('availability_question_employee', 0);
    }

    public function test_show_payload_lists_questions_and_answered_ids(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);
        $a = AvailabilityQuestion::factory()->create(['text' => 'A', 'position' => 1]);
        AvailabilityQuestion::factory()->create(['text' => 'B', 'position' => 2]);
        $employee->availabilityQuestions()->attach($a);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->has('questions', 2)
                ->where('questions.0.text', 'A')
                ->where('questionAnswers', [$a->id])
            );
    }
}
