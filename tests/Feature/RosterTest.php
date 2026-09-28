<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    private const THIS_WEEK = '2026-09-21';

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

    // ── Access and week ─────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/roster')->assertRedirect('/login');
    }

    public function test_a_manager_may_view_the_roster(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/roster')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Roster'));
    }

    public function test_an_admin_may_view_the_roster(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/roster')->assertOk();
    }

    public function test_the_current_week_is_the_default(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/roster')->assertInertia(fn ($page) => $page
            ->where('weekStart', self::THIS_WEEK)
            ->where('weekNumber', 39)
            ->where('today', '2026-09-23')
            ->where('days', [
                '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24',
                '2026-09-25', '2026-09-26', '2026-09-27',
            ]));
    }

    public function test_the_week_query_selects_the_week_of_that_date(): void
    {
        $this->actingAs(User::factory()->create());

        // A Thursday snaps back to its Monday.
        $this->get('/roster?week=2026-10-08')->assertInertia(fn ($page) => $page
            ->where('weekStart', '2026-10-05')
            ->where('weekNumber', 41));
    }

    public function test_an_invalid_week_query_falls_back_to_the_current_week(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/roster?week=nonsense')->assertInertia(fn ($page) => $page
            ->where('weekStart', self::THIS_WEEK));
    }
}
