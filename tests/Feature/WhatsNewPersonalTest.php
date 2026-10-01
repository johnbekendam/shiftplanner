<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanningSettings;
use App\Services\MarkdownRenderer;
use App\Services\WhatsNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WhatsNewPersonalTest extends TestCase
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

    /** @return array{Employee, string} */
    private function linkedEmployee(?string $seenAt): array
    {
        $employee = tap(Employee::factory()->create())->update(['whats_new_seen_at' => $seenAt]);
        $link = $employee->personalLink()->create(['token' => 'tok-'.$employee->id]);

        return [$employee, $link->token];
    }

    public function test_the_personal_page_gets_the_employee_entries_and_the_seen_date(): void
    {
        $this->entry('2026-09-01', 'employee');
        $this->entry('2026-09-02', 'manager');
        [, $token] = $this->linkedEmployee('2026-08-01');

        $this->get("/personal/{$token}")->assertInertia(fn ($page) => $page
            ->has('whatsNew.entries', 1)
            ->where('whatsNew.entries.0.title', 'For employee')
            ->where('whatsNew.seenAt', '2026-08-01')
        );
    }

    public function test_marking_as_seen_stores_the_newest_employee_entry_also_when_changes_are_locked(): void
    {
        $this->entry('2026-09-01', 'employee');
        $this->entry('2026-09-05', 'manager');
        PlanningSettings::current()->update(['allow_employee_changes' => false]);
        [$employee, $token] = $this->linkedEmployee(null);

        $this->post("/personal/{$token}/whats-new/seen")->assertNoContent();

        $this->assertSame('2026-09-01', $employee->fresh()->whats_new_seen_at->toDateString());
    }

    public function test_marking_as_seen_with_an_unknown_token_is_not_found(): void
    {
        $this->post('/personal/unknown/whats-new/seen')->assertNotFound();
    }
}
