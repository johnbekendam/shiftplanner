<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Services\Placeholders\PlaceholderRegistry;

/**
 * Resolves App\Services\Placeholders\PlaceholderRegistry tokens against one
 * recipient (an Employee or a User), for any composable MessageType. Only
 * tokens actually present in the given text are resolved. A token whose
 * resolver returns null for this recipient is reported as unresolved and
 * left untouched in the text — callers decide what to do with that
 * recipient (see MailboxController@store's pre-send warning).
 */
class MessagePlaceholders
{
    public function __construct(private PlaceholderRegistry $registry) {}

    /** Every registry token, for the Compose UI's "insert placeholder" affordance. */
    public function tokens(): array
    {
        return collect($this->registry->definitions())->pluck('token')->all();
    }

    /**
     * @return array{subject: string, body: string, unresolved: string[]}
     */
    public function resolve(string $subject, string $body, Employee|User $recipient): array
    {
        $text = $subject.$body;
        $map = [];
        $unresolved = [];

        foreach ($this->registry->definitions() as $definition) {
            if (! str_contains($text, $definition['token'])) {
                continue;
            }

            $value = $recipient instanceof Employee
                ? $definition['resolveEmployee']($recipient)
                : $definition['resolveUser']($recipient);

            if ($value === null) {
                $unresolved[] = $definition['token'];

                continue;
            }

            $map[$definition['token']] = $value;
        }

        return [
            'subject' => $this->apply($subject, $map),
            'body' => $this->apply($body, $map),
            'unresolved' => $unresolved,
        ];
    }

    /** Placeholder => value map for a preview with no recipient selected. */
    public function sample(): array
    {
        return collect($this->registry->definitions())
            ->mapWithKeys(fn (array $definition) => [$definition['token'] => $definition['sample']])
            ->all();
    }

    /** Apply a resolved placeholder map to one string. */
    public function apply(string $text, array $map): string
    {
        if (isset($map[':link'])) {
            $text = str_replace(
                ':button:link',
                ':button['.__('mailbox.button.view_personal_page').']('.$map[':link'].')',
                $text,
            );
        }

        return strtr($text, $map);
    }
}
