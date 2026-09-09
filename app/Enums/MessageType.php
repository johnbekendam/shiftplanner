<?php

namespace App\Enums;

/**
 * The kinds of message the mailbox can compose and send. Every Message and
 * every MessageTemplate row carries one. The set grows over time; the DB
 * column is a plain string validated against this enum rather than a
 * database enum, so a new case needs no schema change.
 */
enum MessageType: string
{
    case PersonalPageLink = 'personal_page_link';

    /** Placeholder tokens this type's template may use, resolved per recipient. */
    public function placeholders(): array
    {
        return match ($this) {
            self::PersonalPageLink => [':name', ':link'],
        };
    }

    /** Whether composing this type selects employees instead of free addresses. */
    public function needsEmployees(): bool
    {
        return match ($this) {
            self::PersonalPageLink => true,
        };
    }

    /** The flat i18n key prefix for this type's label and seed template. */
    public function langKey(): string
    {
        return 'mailbox.type.'.$this->value;
    }
}
