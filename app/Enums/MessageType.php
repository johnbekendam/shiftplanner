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

    // Account login-links (features/login-links/): system-triggered, never
    // manually composed — see needsEmployees() below.
    case UserInvite = 'user_invite';

    case UserLoginLink = 'user_login_link';

    /** Placeholder tokens this type's template may use, resolved per recipient. */
    public function placeholders(): array
    {
        return match ($this) {
            self::PersonalPageLink, self::UserLoginLink => [':name', ':link'],
            // :sender_name is the inviting admin's first name.
            self::UserInvite => [':name', ':link', ':sender_name'],
        };
    }

    /**
     * Whether composing this type selects employees instead of free
     * addresses. Also gates whether the type appears in the Compose tab's
     * type list at all — a type with no employee concept (an account
     * link) is issued only by its own service, never composed by hand.
     */
    public function needsEmployees(): bool
    {
        return match ($this) {
            self::PersonalPageLink => true,
            self::UserInvite, self::UserLoginLink => false,
        };
    }

    /** The flat i18n key prefix for this type's label and seed template. */
    public function langKey(): string
    {
        return 'mailbox.type.'.$this->value;
    }
}
