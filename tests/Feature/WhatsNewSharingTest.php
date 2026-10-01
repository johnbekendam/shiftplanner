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

    public function test_a_manager_gets_the_unseen_count_of_the_manager_entries(): void
    {
        $this->entry('2026-08-01', 'manager');
        $this->entry('2026-09-01', 'manager');
        $this->entry('2026-09-02', 'admin');
        $this->actingAs(tap(User::factory()->create())->update(['whats_new_seen_at' => '2026-08-01']));

        $this->get('/schedule')->assertInertia(fn ($page) => $page
            ->where('whatsNew', ['unseen' => 1, 'hasEntries' => true])
        );
    }

    public function test_an_admin_also_counts_the_admin_entries(): void
    {
        $this->entry('2026-09-01', 'manager');
        $this->entry('2026-09-02', 'admin');
        $this->actingAs(tap(User::factory()->admin()->create())->update(['whats_new_seen_at' => null]));

        $this->get('/schedule')->assertInertia(fn ($page) => $page->where('whatsNew.unseen', 2));
    }

    public function test_a_user_without_entries_gets_no_entries(): void
    {
        $this->entry('2026-09-02', 'admin');
        $this->actingAs(User::factory()->create());

        $this->get('/schedule')->assertInertia(fn ($page) => $page
            ->where('whatsNew', ['unseen' => 0, 'hasEntries' => false])
        );
    }

    public function test_a_guest_gets_nothing(): void
    {
        $this->entry('2026-09-01', 'manager');

        $this->get('/login')->assertInertia(fn ($page) => $page->where('whatsNew', null));
    }

    public function test_the_separate_seen_route_is_gone(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/whats-new/seen')->assertNotFound();
    }
}
