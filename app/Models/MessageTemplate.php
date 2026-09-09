<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Model;

/**
 * One editable template per message type: a subject and a Markdown body
 * with per-type placeholders (see MessageType::placeholders()). Seeded from
 * the language file on first read, then owned by the admin who edits it on
 * the Compose tab.
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
                'subject' => __($type->langKey().'.subject'),
                'body' => __($type->langKey().'.body'),
            ],
        );
    }
}
