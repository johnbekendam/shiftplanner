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
}
