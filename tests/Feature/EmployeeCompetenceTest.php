<?php

namespace Tests\Feature;

use App\Models\Competence;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCompetenceTest extends TestCase
{
    use RefreshDatabase;

    private function token(Employee $employee): string
    {
        return $employee->personalLink()->create(['token' => 'tok-'.$employee->id])->token;
    }

    // ── Manager route ────────────────────────────────────────────────────

    public function test_guest_cannot_toggle_a_competence(): void
    {
        $employee = Employee::factory()->create();
        $competence = Competence::factory()->create();

        $this->put("/employees/{$employee->id}/competences/{$competence->id}")
            ->assertRedirect('/login');

        $this->assertDatabaseCount('competence_employee', 0);
    }

    public function test_manager_attaches_then_detaches_a_competence(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $competence = Competence::factory()->create();
        $base = "/employees/{$employee->id}/competences/{$competence->id}";

        $this->actingAs($user)->put($base)->assertRedirect();
        $this->assertDatabaseHas('competence_employee', [
            'employee_id' => $employee->id,
            'competence_id' => $competence->id,
        ]);

        $this->actingAs($user)->delete($base)->assertRedirect();
        $this->assertDatabaseCount('competence_employee', 0);
    }

    public function test_attaching_twice_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $competence = Competence::factory()->create();
        $base = "/employees/{$employee->id}/competences/{$competence->id}";

        $this->actingAs($user)->put($base);
        $this->actingAs($user)->put($base);

        $this->assertDatabaseCount('competence_employee', 1);
    }

    public function test_detaching_a_competence_the_employee_does_not_hold_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $competence = Competence::factory()->create();

        $this->actingAs($user)
            ->delete("/employees/{$employee->id}/competences/{$competence->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('competence_employee', 0);
    }

    public function test_an_unknown_competence_is_not_found(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->put("/employees/{$employee->id}/competences/9999")->assertNotFound();
    }

    public function test_edit_payload_lists_all_competences_and_the_held_ids(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $b = Competence::factory()->create(['name' => 'B', 'position' => 2]);
        $a = Competence::factory()->create(['name' => 'A', 'position' => 1]);
        $employee->competences()->attach($b);

        $this->actingAs($user)->get("/employees/{$employee->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Employees/Form')
                ->has('competences', 2)
                ->where('competences.0.name', 'A')
                ->where('competences.1.name', 'B')
                ->where('competenceIds', [$b->id])
            );
    }

    // ── Personal route ──────────────────────────────────────────────────

    public function test_employee_toggles_a_competence_by_token(): void
    {
        $employee = Employee::factory()->create();
        $competence = Competence::factory()->create();
        $token = $this->token($employee);
        $base = "/personal/{$token}/competences/{$competence->id}";

        $this->put($base)->assertRedirect("/personal/{$token}");
        $this->assertDatabaseHas('competence_employee', [
            'employee_id' => $employee->id,
            'competence_id' => $competence->id,
        ]);

        $this->delete($base)->assertRedirect("/personal/{$token}");
        $this->assertDatabaseCount('competence_employee', 0);
    }

    public function test_a_bad_token_is_404(): void
    {
        $competence = Competence::factory()->create();

        $this->put("/personal/not-a-token/competences/{$competence->id}")->assertNotFound();
        $this->assertDatabaseCount('competence_employee', 0);
    }

    public function test_show_payload_lists_all_competences_and_the_held_ids(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->token($employee);
        $a = Competence::factory()->create(['name' => 'A', 'position' => 1]);
        Competence::factory()->create(['name' => 'B', 'position' => 2]);
        $employee->competences()->attach($a);

        $this->get("/personal/{$token}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personal/Show')
                ->has('competences', 2)
                ->where('competences.0.name', 'A')
                ->where('competenceIds', [$a->id])
            );
    }
}
