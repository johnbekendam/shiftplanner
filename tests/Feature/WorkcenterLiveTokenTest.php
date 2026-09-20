<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkcenterLiveTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_workcenter_gets_a_live_token(): void
    {
        $workcenter = Workcenter::factory()->create();

        $this->assertNotNull($workcenter->fresh()->live_token);
        $this->assertSame(40, strlen($workcenter->fresh()->live_token));
    }

    public function test_each_workcenter_gets_its_own_token(): void
    {
        $tokens = Workcenter::factory()->count(3)->create()->pluck('live_token');

        $this->assertSame(3, $tokens->unique()->count());
    }

    public function test_creating_a_workcenter_from_settings_gives_it_a_token(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/settings/workcenters', ['name' => 'Line 1'])->assertRedirect();

        $this->assertNotNull(Workcenter::where('name', 'Line 1')->first()->live_token);
    }

    public function test_an_explicit_token_is_kept(): void
    {
        $workcenter = Workcenter::factory()->create(['live_token' => 'fixed-token']);

        $this->assertSame('fixed-token', $workcenter->fresh()->live_token);
    }

    // ── Regenerate ──────────────────────────────────────────────────────

    public function test_guest_cannot_regenerate_a_live_token(): void
    {
        $workcenter = Workcenter::factory()->create();
        $token = $workcenter->live_token;

        $this->post("/settings/workcenters/{$workcenter->id}/live-token")->assertRedirect('/login');
        $this->assertSame($token, $workcenter->fresh()->live_token);
    }

    public function test_manager_cannot_regenerate_a_live_token(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        $token = $workcenter->live_token;

        $this->post("/settings/workcenters/{$workcenter->id}/live-token")->assertForbidden();
        $this->assertSame($token, $workcenter->fresh()->live_token);
    }

    public function test_admin_regenerates_the_token_and_the_old_url_stops_working(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $workcenter = Workcenter::factory()->create();
        $old = $workcenter->live_token;

        $this->post("/settings/workcenters/{$workcenter->id}/live-token")
            ->assertRedirect()
            ->assertSessionHas('success');

        $new = $workcenter->fresh()->live_token;
        $this->assertNotSame($old, $new);
        $this->assertSame(40, strlen($new));
        $this->get("/live/{$old}")->assertNotFound();
        $this->get("/live/{$new}")->assertOk();
    }

    public function test_regenerating_leaves_other_workcenters_alone(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$first, $second] = Workcenter::factory()->count(2)->create();
        $secondToken = $second->live_token;

        $this->post("/settings/workcenters/{$first->id}/live-token");

        $this->assertSame($secondToken, $second->fresh()->live_token);
    }

    public function test_regenerating_an_unknown_workcenter_returns_404(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post('/settings/workcenters/999/live-token')->assertNotFound();
    }

    // ── Settings payload ────────────────────────────────────────────────

    public function test_settings_lists_the_live_url_of_each_workcenter(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $workcenter = Workcenter::factory()->create();

        $this->get('/settings')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workcenters.0.live_url', url("/live/{$workcenter->live_token}")));
    }
}
