<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Resolves the :name and :link placeholders for a personal_page_link
 * message. Resolution happens once, when the message is created, so the
 * stored, previewed, and sent text are identical.
 */
class PersonalLinkMessage
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    /** Placeholder => value map for one employee, ensuring their link exists. */
    public function forEmployee(Employee $employee): array
    {
        return [
            ':name' => $employee->name,
            ':link' => $this->links->linkFor($employee),
        ];
    }

    /** Placeholder => value map for a preview with no employee selected. */
    public function sample(): array
    {
        return [
            ':name' => __('mailbox.preview.sample_name'),
            ':link' => url('/personal/EXAMPLE-TOKEN'),
        ];
    }

    /** Apply a placeholder map to one string. */
    public function apply(string $text, array $map): string
    {
        return strtr($text, $map);
    }
}
