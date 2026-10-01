<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\MarkdownRenderer;
use App\Services\WhatsNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WhatsNewSeenStateTest extends TestCase
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

    private function entry(string $date): void
    {
        File::put("{$this->directory}/{$date}.md", "---\ndate: {$date}\ntitle: Entry\naudience: employee\n---\nBody\n");
    }

    public function test_a_new_user_starts_at_the_newest_entry(): void
    {
        $this->entry('2026-09-01');
        $this->entry('2026-10-01');

        $this->assertSame('2026-10-01', User::factory()->create()->fresh()->whats_new_seen_at->toDateString());
    }

    public function test_a_new_employee_starts_at_the_newest_entry(): void
    {
        $this->entry('2026-10-01');

        $this->assertSame('2026-10-01', Employee::factory()->create()->fresh()->whats_new_seen_at->toDateString());
    }

    public function test_a_new_person_without_entries_has_no_seen_date(): void
    {
        $this->assertNull(User::factory()->create()->fresh()->whats_new_seen_at);
        $this->assertNull(Employee::factory()->create()->fresh()->whats_new_seen_at);
    }

    public function test_an_explicit_seen_date_is_kept(): void
    {
        $this->entry('2026-10-01');

        $user = User::factory()->create(['whats_new_seen_at' => '2026-01-01']);

        $this->assertSame('2026-01-01', $user->fresh()->whats_new_seen_at->toDateString());
    }
}
