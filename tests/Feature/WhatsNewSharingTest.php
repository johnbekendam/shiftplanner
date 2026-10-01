<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarkdownRenderer;
use App\Services\WhatsNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WhatsNewSharingTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/whats-new-'.uniqid();
        File::makeDirectory($this->directory);
        $this->app->instance(WhatsNew::class, new WhatsNew(new MarkdownRenderer, $this->directory));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function entry(string $date, string $audience): void
    {
        File::put("{$this->directory}/{$date}.md", "---\ndate: {$date}\ntitle: For {$audience}\naudience: {$audience}\n---\nBody\n");
    }

    public function test_a_manager_gets_the_manager_entries_and_the_seen_date(): void
    {
        $this->entry('2026-09-01', 'manager');
        $this->entry('2026-09-02', 'admin');
        $this->actingAs(User::factory()->create(['whats_new_seen_at' => '2026-08-01']));

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->has('whatsNew.entries', 1)
            ->where('whatsNew.entries.0.title', 'For manager')
            ->where('whatsNew.seenAt', '2026-08-01')
        );
    }

    public function test_an_admin_also_gets_the_admin_entries(): void
    {
        $this->entry('2026-09-01', 'manager');
        $this->entry('2026-09-02', 'admin');
        $this->entry('2026-09-03', 'employee');
        $this->actingAs(tap(User::factory()->admin()->create())->update(['whats_new_seen_at' => null]));

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->has('whatsNew.entries', 2)
            ->where('whatsNew.seenAt', null)
        );
    }

    public function test_a_guest_gets_no_entries(): void
    {
        $this->entry('2026-09-01', 'manager');

        $this->get('/login')->assertInertia(fn ($page) => $page->where('whatsNew', null));
    }

    public function test_marking_as_seen_stores_the_date_of_the_newest_entry_of_the_user(): void
    {
        $this->entry('2026-09-01', 'manager');
        $this->entry('2026-09-05', 'employee');
        $user = tap(User::factory()->create())->update(['whats_new_seen_at' => null]);
        $this->actingAs($user);

        $this->post('/whats-new/seen')->assertNoContent();

        $this->assertSame('2026-09-01', $user->fresh()->whats_new_seen_at->toDateString());
    }

    public function test_a_guest_cannot_mark_as_seen(): void
    {
        $this->post('/whats-new/seen')->assertRedirect('/login');
    }
}
