<?php

namespace Tests\Feature;

use App\Models\BusinessLine;
use App\Models\Competence;
use App\Models\PlanningRule;
use App\Models\Shift;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningRuleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_manage_planning_rules(): void
    {
        $this->post('/planning-rules', ['type' => 'max_shifts_per_day', 'mode' => 'hard', 'value' => 1])
            ->assertRedirect('/login');
    }

    public function test_manager_cannot_manage_planning_rules(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/planning-rules', ['type' => 'max_shifts_per_day', 'mode' => 'hard', 'value' => 1])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_index(): void
    {
        $this->get('/planning-rules')->assertRedirect('/login');
    }

    public function test_manager_is_forbidden_from_the_index(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/planning-rules')->assertForbidden();
    }

    public function test_index_carries_existing_rules_and_lookup_lists(): void
    {
        $this->actingAsAdmin();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);
        $workcenter = Workcenter::factory()->create();

        $this->get('/planning-rules')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('planningRules', 1)
                ->where('planningRules.0.type', 'max_hours_per_week')
                ->has('workcenters', 1)
                ->where('workcenters.0.id', $workcenter->id)
            );
    }

    public function test_admin_creates_a_singleton_rule_with_no_extra_config(): void
    {
        $this->actingAsAdmin();

        $this->post('/planning-rules', ['type' => 'max_hours_per_week', 'mode' => 'hard'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $rule = PlanningRule::sole();
        $this->assertSame('max_hours_per_week', $rule->type);
        $this->assertSame('hard', $rule->mode);
        $this->assertNull($rule->severity);
        $this->assertSame([], $rule->config);
    }

    public function test_admin_creates_max_shifts_per_day_with_a_value(): void
    {
        $this->actingAsAdmin();

        $this->post('/planning-rules', ['type' => 'max_shifts_per_day', 'mode' => 'soft', 'severity' => 6, 'value' => 2])
            ->assertRedirect()->assertSessionHasNoErrors();

        $rule = PlanningRule::sole();
        $this->assertSame('soft', $rule->mode);
        $this->assertSame(6, $rule->severity);
        $this->assertSame(['value' => 2], $rule->config);
    }

    public function test_a_second_rule_of_the_same_singleton_type_is_rejected(): void
    {
        $this->actingAsAdmin();
        PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->post('/planning-rules', ['type' => 'max_hours_per_week', 'mode' => 'hard'])
            ->assertSessionHasErrors('type');
    }

    public function test_soft_without_a_severity_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/planning-rules', ['type' => 'not_preferred_shift', 'mode' => 'soft'])
            ->assertSessionHasErrors('severity');
    }

    public function test_hard_with_a_severity_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/planning-rules', ['type' => 'not_preferred_shift', 'mode' => 'hard', 'severity' => 5])
            ->assertSessionHasErrors('severity');
    }

    public function test_admin_creates_a_competence_requirement(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $competence = Competence::factory()->create();

        $this->post('/planning-rules', [
            'type' => 'competence_required',
            'mode' => 'hard',
            'workcenter_id' => $workcenter->id,
            'competence_id' => $competence->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rule = PlanningRule::sole();
        $this->assertSame(['workcenter_id' => $workcenter->id, 'competence_id' => $competence->id], $rule->config);
    }

    public function test_a_duplicate_competence_requirement_is_rejected(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $competence = Competence::factory()->create();
        PlanningRule::create([
            'type' => 'competence_required',
            'mode' => 'hard',
            'config' => ['workcenter_id' => $workcenter->id, 'competence_id' => $competence->id],
        ]);

        $this->post('/planning-rules', [
            'type' => 'competence_required',
            'mode' => 'hard',
            'workcenter_id' => $workcenter->id,
            'competence_id' => $competence->id,
        ])->assertSessionHasErrors('competence_id');
    }

    public function test_admin_creates_a_business_line_preference(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $lineA = BusinessLine::factory()->create();

        $this->post('/planning-rules', [
            'type' => 'business_line_preference',
            'mode' => 'soft',
            'severity' => 4,
            'workcenter_id' => $workcenter->id,
            'business_line_id' => $lineA->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rule = PlanningRule::sole();
        $this->assertSame($workcenter->id, $rule->config['workcenter_id']);
    }

    public function test_a_second_business_line_preference_for_the_same_workcenter_is_rejected(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $line = BusinessLine::factory()->create();
        PlanningRule::create([
            'type' => 'business_line_preference',
            'severity' => 3,
            'config' => ['workcenter_id' => $workcenter->id, 'business_line_id' => $line->id],
        ]);

        $this->post('/planning-rules', [
            'type' => 'business_line_preference',
            'mode' => 'soft',
            'severity' => 3,
            'workcenter_id' => $workcenter->id,
            'business_line_id' => $line->id,
        ])->assertSessionHasErrors('workcenter_id');
    }

    public function test_update_changes_mode_and_severity(): void
    {
        $this->actingAsAdmin();
        $rule = PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->put("/planning-rules/{$rule->id}", ['mode' => 'soft', 'severity' => 7])
            ->assertRedirect()->assertSessionHasNoErrors();

        $rule->refresh();
        $this->assertSame('soft', $rule->mode);
        $this->assertSame(7, $rule->severity);
    }

    public function test_update_ignores_client_supplied_identity_fields(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $otherWorkcenter = Workcenter::factory()->create();
        $shift = Shift::factory()->create();
        $competence = Competence::factory()->create();
        $rule = PlanningRule::create([
            'type' => 'competence_required',
            'mode' => 'hard',
            'config' => ['workcenter_id' => $workcenter->id, 'competence_id' => $competence->id],
        ]);

        $this->put("/planning-rules/{$rule->id}", [
            'mode' => 'hard',
            'workcenter_id' => $otherWorkcenter->id,
            'competence_id' => $competence->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($workcenter->id, $rule->refresh()->config['workcenter_id']);
    }

    public function test_update_changes_a_business_line_preferences_business_lines(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        $lineA = BusinessLine::factory()->create();
        $lineB = BusinessLine::factory()->create();
        $rule = PlanningRule::create([
            'type' => 'business_line_preference',
            'mode' => 'soft',
            'severity' => 5,
            'config' => ['workcenter_id' => $workcenter->id, 'business_line_id' => $lineA->id],
        ]);

        $this->put("/planning-rules/{$rule->id}", [
            'mode' => 'soft',
            'severity' => 5,
            'business_line_id' => $lineB->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($lineB->id, $rule->refresh()->config['business_line_id']);
    }

    public function test_destroy_removes_the_rule(): void
    {
        $this->actingAsAdmin();
        $rule = PlanningRule::create(['type' => 'max_hours_per_week', 'mode' => 'hard']);

        $this->delete("/planning-rules/{$rule->id}")->assertRedirect();

        $this->assertModelMissing($rule);
    }
}
