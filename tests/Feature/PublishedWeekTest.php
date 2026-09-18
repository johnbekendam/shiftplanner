<?php

namespace Tests\Feature;

use App\Models\PublishedWeek;
use App\Models\User;
use App\Models\Workcenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedWeekTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        return $user;
    }

    private function url(Workcenter $workcenter, string $weekStart = '2026-09-14'): string
    {
        return "/planning/weeks/{$weekStart}/workcenters/{$workcenter->id}/publish";
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_publish(): void
    {
        $workcenter = Workcenter::factory()->create();

        $this->post($this->url($workcenter))->assertRedirect('/login');
        $this->assertSame(0, PublishedWeek::count());
    }

    public function test_manager_cannot_publish(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();

        $this->post($this->url($workcenter))->assertForbidden();
    }

    public function test_guest_cannot_unpublish(): void
    {
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $workcenter->id]);

        $this->delete($this->url($workcenter))->assertRedirect('/login');
        $this->assertSame(1, PublishedWeek::count());
    }

    public function test_manager_cannot_unpublish(): void
    {
        $this->actingAs(User::factory()->create());
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $workcenter->id]);

        $this->delete($this->url($workcenter))->assertForbidden();
    }

    // ── Publish ─────────────────────────────────────────────────────────

    public function test_store_publishes_the_week_for_that_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();

        $this->post($this->url($workcenter))->assertRedirect();

        $this->assertDatabaseHas('published_weeks', [
            'week_start' => '2026-09-14',
            'workcenter_id' => $workcenter->id,
        ]);
    }

    public function test_republishing_an_already_published_week_is_idempotent(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $workcenter->id]);

        $this->post($this->url($workcenter))->assertRedirect();

        $this->assertSame(1, PublishedWeek::count());
    }

    public function test_publishing_one_workcenter_does_not_publish_another(): void
    {
        $this->actingAsAdmin();
        $published = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();

        $this->post($this->url($published))->assertRedirect();

        $this->assertDatabaseHas('published_weeks', ['week_start' => '2026-09-14', 'workcenter_id' => $published->id]);
        $this->assertDatabaseMissing('published_weeks', ['week_start' => '2026-09-14', 'workcenter_id' => $other->id]);
    }

    // ── Unpublish ───────────────────────────────────────────────────────

    public function test_destroy_unpublishes_the_week_for_that_workcenter(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $workcenter->id]);

        $this->delete($this->url($workcenter))->assertRedirect();

        $this->assertSame(0, PublishedWeek::count());
    }

    public function test_destroy_is_a_no_op_when_not_published(): void
    {
        $this->actingAsAdmin();
        $workcenter = Workcenter::factory()->create();

        $this->delete($this->url($workcenter))->assertRedirect();

        $this->assertSame(0, PublishedWeek::count());
    }

    public function test_unpublishing_one_workcenter_does_not_affect_another(): void
    {
        $this->actingAsAdmin();
        $unpublishing = Workcenter::factory()->create();
        $other = Workcenter::factory()->create();
        PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $unpublishing->id]);
        PublishedWeek::query()->create(['week_start' => '2026-09-14', 'workcenter_id' => $other->id]);

        $this->delete($this->url($unpublishing))->assertRedirect();

        $this->assertDatabaseMissing('published_weeks', ['week_start' => '2026-09-14', 'workcenter_id' => $unpublishing->id]);
        $this->assertDatabaseHas('published_weeks', ['week_start' => '2026-09-14', 'workcenter_id' => $other->id]);
    }
}
