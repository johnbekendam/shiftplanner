<?php

namespace Tests\Feature;

use App\Models\PublishedWeek;
use App\Models\User;
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

    private function url(string $weekStart = '2026-09-14'): string
    {
        return "/planning/weeks/{$weekStart}/publish";
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_guest_cannot_publish(): void
    {
        $this->post($this->url())->assertRedirect('/login');
        $this->assertSame(0, PublishedWeek::count());
    }

    public function test_manager_cannot_publish(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post($this->url())->assertForbidden();
    }

    public function test_guest_cannot_unpublish(): void
    {
        PublishedWeek::query()->create(['week_start' => '2026-09-14']);

        $this->delete($this->url())->assertRedirect('/login');
        $this->assertSame(1, PublishedWeek::count());
    }

    public function test_manager_cannot_unpublish(): void
    {
        $this->actingAs(User::factory()->create());
        PublishedWeek::query()->create(['week_start' => '2026-09-14']);

        $this->delete($this->url())->assertForbidden();
    }

    // ── Publish ─────────────────────────────────────────────────────────

    public function test_store_publishes_the_week(): void
    {
        $this->actingAsAdmin();

        $this->post($this->url())->assertRedirect();

        $this->assertDatabaseHas('published_weeks', ['week_start' => '2026-09-14']);
    }

    public function test_republishing_an_already_published_week_is_idempotent(): void
    {
        $this->actingAsAdmin();
        PublishedWeek::query()->create(['week_start' => '2026-09-14']);

        $this->post($this->url())->assertRedirect();

        $this->assertSame(1, PublishedWeek::count());
    }

    // ── Unpublish ───────────────────────────────────────────────────────

    public function test_destroy_unpublishes_the_week(): void
    {
        $this->actingAsAdmin();
        PublishedWeek::query()->create(['week_start' => '2026-09-14']);

        $this->delete($this->url())->assertRedirect();

        $this->assertSame(0, PublishedWeek::count());
    }

    public function test_destroy_is_a_no_op_when_not_published(): void
    {
        $this->actingAsAdmin();

        $this->delete($this->url())->assertRedirect();

        $this->assertSame(0, PublishedWeek::count());
    }
}
