<?php

namespace Tests\Feature;

use App\Models\PlanningSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_update_the_period(): void
    {
        $this->put('/settings/period', ['fte_hours' => 40])->assertRedirect('/login');
    }

    public function test_manager_cannot_update_the_period(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/settings/period', ['fte_hours' => 40])->assertForbidden();
    }

    public function test_settings_index_carries_the_period_defaults(): void
    {
        $this->actingAsAdmin();

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('period.fte_hours', 40)
                ->where('period.period_start', null)
                ->where('period.period_end', null)
            );
    }

    public function test_admin_saves_the_period(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/period', [
            'fte_hours' => 36,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'allow_employee_changes' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = PlanningSettings::current();
        $this->assertSame(36, $settings->fte_hours);
        $this->assertSame('2026-01-01', $settings->period_start->toDateString());
        $this->assertSame('2026-03-31', $settings->period_end->toDateString());
    }

    public function test_blank_dates_are_accepted(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/period', [
            'fte_hours' => 40,
            'period_start' => null,
            'period_end' => null,
            'allow_employee_changes' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull(PlanningSettings::current()->period_start);
    }

    public function test_fte_hours_below_one_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/period', ['fte_hours' => 0])->assertSessionHasErrors('fte_hours');
    }

    public function test_an_end_before_the_start_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put('/settings/period', [
            'fte_hours' => 40,
            'period_start' => '2026-03-31',
            'period_end' => '2026-01-01',
        ])->assertSessionHasErrors('period_end');
    }
}
