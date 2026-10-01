<?php

namespace Tests\Unit;

use App\Services\MarkdownRenderer;
use App\Services\WhatsNew;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WhatsNewTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/whats-new-'.uniqid();
        File::makeDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function entry(string $file, string $date, string $title, string $audience, string $body = 'Body'): void
    {
        File::put("{$this->directory}/{$file}", "---\ndate: {$date}\ntitle: {$title}\naudience: {$audience}\n---\n{$body}\n");
    }

    private function whatsNew(): WhatsNew
    {
        return new WhatsNew(new MarkdownRenderer, $this->directory);
    }

    public function test_it_reads_the_front_matter_and_renders_the_body(): void
    {
        $this->entry('2026-10-01-demand.md', '2026-10-01', 'Demand page', 'manager', 'A **new** page.');

        $this->assertSame([[
            'id' => '2026-10-01-demand',
            'date' => '2026-10-01',
            'title' => 'Demand page',
            'html' => "<p>A <strong>new</strong> page.</p>\n",
        ]], $this->whatsNew()->for('manager'));
    }

    public function test_it_sorts_the_entries_newest_first(): void
    {
        $this->entry('a.md', '2026-09-01', 'Old', 'manager');
        $this->entry('b.md', '2026-10-01', 'New', 'manager');

        $this->assertSame(['New', 'Old'], array_column($this->whatsNew()->for('manager'), 'title'));
    }

    public function test_it_filters_by_audience_and_gives_an_admin_the_manager_entries_too(): void
    {
        $this->entry('a.md', '2026-10-01', 'Admin only', 'admin');
        $this->entry('b.md', '2026-10-02', 'Managers', 'manager');
        $this->entry('c.md', '2026-10-03', 'Both', 'admin, manager');

        $this->assertSame(['Both', 'Managers', 'Admin only'], array_column($this->whatsNew()->for('admin'), 'title'));
        $this->assertSame(['Both', 'Managers'], array_column($this->whatsNew()->for('manager'), 'title'));
    }

    public function test_employees_get_no_entries(): void
    {
        $this->entry('a.md', '2026-10-01', 'Employees', 'employee');

        $this->assertSame([], $this->whatsNew()->for('employee'));
    }

    public function test_it_gives_the_date_of_the_newest_entry_of_any_audience(): void
    {
        $this->entry('a.md', '2026-09-01', 'Old', 'manager');
        $this->entry('b.md', '2026-10-01', 'New', 'admin');

        $this->assertSame('2026-10-01', $this->whatsNew()->latestDate());
    }

    public function test_it_has_no_entries_and_no_latest_date_without_files(): void
    {
        $this->assertSame([], $this->whatsNew()->for('manager'));
        $this->assertNull($this->whatsNew()->latestDate());
        $this->assertSame([], (new WhatsNew(new MarkdownRenderer, $this->directory.'/missing'))->for('manager'));
    }

    public function test_it_counts_the_entries_of_a_role_after_a_seen_date(): void
    {
        $this->entry('a.md', '2026-09-01', 'Old', 'manager');
        $this->entry('b.md', '2026-10-01', 'New', 'manager');
        $this->entry('c.md', '2026-10-02', 'Admin', 'admin');

        $this->assertSame(2, $this->whatsNew()->unseenCount('manager', null));
        $this->assertSame(1, $this->whatsNew()->unseenCount('manager', '2026-09-01'));
        $this->assertSame(0, $this->whatsNew()->unseenCount('manager', '2026-10-01'));
        $this->assertSame(1, $this->whatsNew()->unseenCount('admin', '2026-10-01'));
    }
}
