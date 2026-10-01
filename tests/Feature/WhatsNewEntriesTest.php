<?php

namespace Tests\Feature;

use App\Services\WhatsNew;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Guards the real entries in resources/whats-new/: a bad file would silently not show. */
class WhatsNewEntriesTest extends TestCase
{
    public function test_every_entry_file_is_valid_and_reaches_an_audience(): void
    {
        $files = File::glob(resource_path('whats-new/*.md'));
        $this->assertNotEmpty($files);

        $whatsNew = app(WhatsNew::class);
        $shown = collect(['admin', 'manager'])
            ->flatMap(fn (string $role) => array_column($whatsNew->for($role), 'id'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $ids = collect($files)->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))->sort()->values()->all();
        $this->assertSame($ids, $shown);
    }

    public function test_every_entry_uses_only_known_audiences(): void
    {
        foreach (File::glob(resource_path('whats-new/*.md')) as $path) {
            preg_match('/^audience:(.*)$/m', File::get($path), $match);
            $audiences = array_map('trim', explode(',', $match[1] ?? ''));

            $this->assertEmpty(array_diff($audiences, ['admin', 'manager']), basename($path));
        }
    }
}
