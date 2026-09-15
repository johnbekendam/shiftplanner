<?php

namespace Tests\Feature;

use App\Models\PlanningSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningRuleGlobalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'max_hours_per_week_mode' => 'hard',
            'max_hours_per_week_severity' => null,
            'max_shifts_per_day' => 2,
            'max_shifts_per_day_mode' => 'hard',
            'max_shifts_per_day_severity' => null,
            'not_preferred_shift_mode' => 'soft',
            'not_preferred_shift_severity' => 5,
        ], $overrides);
    }

    public function test_guest_cannot_update_planning_rules(): void
    {
        $this->put('/settings/planning-rules', $this->validPayload())->assertRedirect('/login');
    }

    public function test_manager_cannot_update_planning_rules(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/settings/planning-rules', $this->validPayload())->assertForbidden();
    }

    public function test_settings_index_carries_the_planning_rule_defaults(): void
    {
        $this->actingAsAdmin();

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('planningRules.max_hours_per_week_mode', 'hard')
                ->where('planningRules.max_shifts_per_day', 1)
                ->where('planningRules.max_shifts_per_day_mode', 'hard')
                ->where('planningRules.not_preferred_shift_mode', 'soft')
            );
    }

    public function test_admin_saves_all_seven_fields(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/planning-rules', $this->validPayload([
            'max_hours_per_week_mode' => 'soft',
            'max_hours_per_week_severity' => 8,
            'max_shifts_per_day' => 2,
            'not_preferred_shift_severity' => 3,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $settings = PlanningSettings::current();
        $this->assertSame('soft', $settings->max_hours_per_week_mode);
        $this->assertSame(8, $settings->max_hours_per_week_severity);
        $this->assertSame(2, $settings->max_shifts_per_day);
        $this->assertSame('hard', $settings->max_shifts_per_day_mode);
        $this->assertNull($settings->max_shifts_per_day_severity);
        $this->assertSame('soft', $settings->not_preferred_shift_mode);
        $this->assertSame(3, $settings->not_preferred_shift_severity);
    }

    public function test_soft_without_a_severity_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/planning-rules', $this->validPayload([
            'max_hours_per_week_mode' => 'soft',
            'max_hours_per_week_severity' => null,
        ]))->assertSessionHasErrors('max_hours_per_week_severity');
    }

    public function test_hard_with_a_severity_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/planning-rules', $this->validPayload([
            'max_shifts_per_day_mode' => 'hard',
            'max_shifts_per_day_severity' => 4,
        ]))->assertSessionHasErrors('max_shifts_per_day_severity');
    }

    public function test_severity_must_be_between_one_and_ten(): void
    {
        $this->actingAsAdmin();

        foreach ([0, 11] as $value) {
            $this->put('/settings/planning-rules', $this->validPayload([
                'not_preferred_shift_severity' => $value,
            ]))->assertSessionHasErrors('not_preferred_shift_severity');
        }
    }

    public function test_max_shifts_per_day_below_one_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/planning-rules', $this->validPayload([
            'max_shifts_per_day' => 0,
        ]))->assertSessionHasErrors('max_shifts_per_day');
    }

    public function test_an_invalid_mode_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/planning-rules', $this->validPayload([
            'max_hours_per_week_mode' => 'medium',
        ]))->assertSessionHasErrors('max_hours_per_week_mode');
    }
}
