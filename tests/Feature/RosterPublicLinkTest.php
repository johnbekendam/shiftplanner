<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Models\PublishedWeek;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Workcenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterPublicLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday, so the current week starts on Monday 2026-09-21.
        Carbon::setTestNow('2026-09-23 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function token(): string
    {
        return PlanningSettings::current()->rosterToken();
    }

    public function test_the_token_is_made_on_first_use_and_then_kept(): void
    {
        $token = $this->token();

        $this->assertSame(40, strlen($token));
        $this->assertSame($token, PlanningSettings::current()->rosterToken());
    }

    public function test_the_page_opens_by_token_without_login(): void
    {
        $this->get("/roster/{$this->token()}")->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertInertia(fn ($page) => $page
                ->component('RosterPublic')
                ->where('weekStart', '2026-09-21')
                ->where('today', '2026-09-23')
                ->has('days', 7)
                ->has('businessLines')
                ->has('selectedBusinessLines'));

        $this->assertStringContainsString('no-store', $this->get("/roster/{$this->token()}")->headers->get('Cache-Control'));
    }

    public function test_an_unknown_token_is_404(): void
    {
        $this->token();

        $this->get('/roster/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_the_page_shows_the_published_roster_of_the_selected_week(): void
    {
        $workcenter = Workcenter::factory()->create(['name' => 'Assembly']);
        $shift = Shift::factory()->create(['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00']);
        PublishedWeek::create(['week_start' => '2026-09-28', 'workcenter_id' => $workcenter->id]);
        ShiftAssignment::factory()->create([
            'employee_id' => Employee::factory()->create(['first_name' => 'Anna', 'last_name' => 'Smit'])->id,
            'workcenter_id' => $workcenter->id,
            'shift_id' => $shift->id,
            'date' => '2026-09-29',
        ]);

        $rows = $this->get("/roster/{$this->token()}?week=2026-09-28")->viewData('page')['props']['rows'];

        $this->assertSame('Anna Smit', $rows[0]['name']);
        $this->assertSame([['shift' => 'Early', 'workcenter' => 'Assembly']], $rows[0]['days'][1]);
    }
}
