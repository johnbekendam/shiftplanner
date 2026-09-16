<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Model;

/**
 * One editable template per message type: a subject and a Markdown body,
 * with placeholders shared across every type (see
 * App\Services\Placeholders\PlaceholderRegistry). Seeded from the language
 * file on first read, then owned by the admin who edits it on the Compose
 * tab.
 */
class MessageTemplate extends Model
{
    protected $fillable = [
        'type',
        'subject',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
        ];
    }

    /** The stored template for a type, created from the seed default on first read. */
    public static function forType(MessageType $type): self
    {
        return static::firstOrCreate(
            ['type' => $type->value],
            [
                'subject' => static::seedText($type->langKey().'.subject'),
                'body' => static::seedText($type->langKey().'.body'),
            ],
        );
    }

    /**
     * A seed lang key that resolves to no translation (missing, or the file
     * has not been picked up yet) falls back to the key itself — never a
     * fit default for a stored row. Treat that as blank instead.
     */
    private static function seedText(string $key): string
    {
        $value = __($key);

        return $value === $key ? '' : $value;
    }
}
