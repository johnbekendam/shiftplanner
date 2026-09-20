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

    case Custom = 'custom';

    // Lists the employee's upcoming published shifts; sending it marks them
    // informed (features/planning-notifications/).
    case Planning = 'planning';

    // Account login-links (features/login-links/): system-triggered, never
    // manually composed — see composable() below.
    case UserInvite = 'user_invite';

    case UserLoginLink = 'user_login_link';

    /**
     * Whether this type appears in the Compose tab's type list at all. A
     * type with no manual-compose concept (an account link) is issued
     * only by its own service. Placeholder tokens are no longer per-type
     * — see App\Services\Placeholders\PlaceholderRegistry.
     */
    public function composable(): bool
    {
        return match ($this) {
            self::PersonalPageLink, self::Custom, self::Planning => true,
            self::UserInvite, self::UserLoginLink => false,
        };
    }

    /** The flat i18n key prefix for this type's label and seed template. */
    public function langKey(): string
    {
        return 'mailbox.type.'.$this->value;
    }
}
