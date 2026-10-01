<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * The "What's new" entries: one Markdown file per release in
 * resources/whats-new/, with `date`, `title` and `audience` front matter.
 * See features/whats-new/.
 */
class WhatsNew
{
    /** An admin has every screen of a manager, so also gets their entries. */
    private const AUDIENCES = [
        'admin' => ['admin', 'manager'],
        'manager' => ['manager'],
    ];

    private ?array $entries = null;

    public function __construct(private MarkdownRenderer $renderer, private ?string $directory = null)
    {
        $this->directory ??= resource_path('whats-new');
    }

    /** The entries for a role (admin or manager), newest first: [{ id, date, title, html }]. */
    public function for(string $role): array
    {
        $audiences = self::AUDIENCES[$role] ?? [];

        return collect($this->entries())
            ->filter(fn (array $entry) => array_intersect($entry['audience'], $audiences) !== [])
            ->map(fn (array $entry) => [
                'id' => $entry['id'],
                'date' => $entry['date'],
                'title' => $entry['title'],
                'html' => $entry['html'],
            ])
            ->values()
            ->all();
    }

    /** The number of entries for a role after the seen date; all of them without one. */
    public function unseenCount(string $role, ?string $seenAt): int
    {
        return collect($this->for($role))->filter(fn (array $entry) => $seenAt === null || $entry['date'] > $seenAt)->count();
    }

    /** The date of the newest entry of any audience, or null without entries. */
    public function latestDate(): ?string
    {
        return $this->entries()[0]['date'] ?? null;
    }

    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $files = File::isDirectory($this->directory) ? File::glob("{$this->directory}/*.md") : [];

        return $this->entries = collect($files)
            ->map(fn (string $path) => $this->parse($path))
            ->filter()
            ->sortByDesc(fn (array $entry) => $entry['date'].$entry['id'])
            ->values()
            ->all();
    }

    /** One file as an entry, or null when its front matter lacks a date or title. */
    private function parse(string $path): ?array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', File::get($path), $parts)) {
            return null;
        }

        $meta = [];
        foreach (preg_split('/\R/', $parts[1]) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $meta['date'] ?? '') || ($meta['title'] ?? '') === '') {
            return null;
        }

        return [
            'id' => pathinfo($path, PATHINFO_FILENAME),
            'date' => $meta['date'],
            'title' => $meta['title'],
            'audience' => array_values(array_filter(array_map('trim', explode(',', $meta['audience'] ?? '')))),
            'html' => $this->renderer->render(trim($parts[2]), fn (string $label) => e($label)),
        ];
    }
}
