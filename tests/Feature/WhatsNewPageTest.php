<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarkdownRenderer;
use App\Services\WhatsNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WhatsNewPageTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/whats-new-'.uniqid();
        File::makeDirectory($this->directory);
        $this->app->instance(WhatsNew::class, new WhatsNew(new MarkdownRenderer, $this->directory));
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $date) {
            File::put("{$this->directory}/{$date}.md", "---\ndate: {$date}\ntitle: Release {$date}\naudience: manager\n---\nBody\n");
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function user(?string $seenAt): User
    {
        return tap(User::factory()->create())->update(['whats_new_seen_at' => $seenAt]);
    }

    public function test_the_page_lists_the_entries_and_flags_the_unseen_ones(): void
    {
        $this->actingAs($this->user('2026-08-01'));

        $this->get('/whats-new')->assertOk()->assertInertia(fn ($page) => $page
            ->component('WhatsNew')
            ->has('entries', 3)
            ->where('entries.0.id', '2026-10-01')
            ->where('entries.0.new', true)
            ->where('entries.1.new', true)
            ->where('entries.2.new', false)
            ->where('selectedId', '2026-10-01')
        );
    }

    public function test_the_visit_marks_every_entry_as_seen(): void
    {
        $user = $this->user(null);
        $this->actingAs($user);

        $this->get('/whats-new')->assertOk();

        $this->assertSame('2026-10-01', $user->fresh()->whats_new_seen_at->toDateString());
    }

    public function test_an_entry_in_the_query_is_selected(): void
    {
        $this->actingAs($this->user(null));

        $this->get('/whats-new?entry=2026-09-01')->assertInertia(fn ($page) => $page->where('selectedId', '2026-09-01'));
        $this->get('/whats-new?entry=unknown')->assertInertia(fn ($page) => $page->where('selectedId', '2026-10-01'));
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->get('/whats-new')->assertRedirect('/login');
    }
}
