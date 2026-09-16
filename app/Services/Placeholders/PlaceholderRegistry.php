<?php

namespace App\Services\Placeholders;

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeePersonalLinkService;

/**
 * The placeholder tokens a Compose subject/body may use, usable in every
 * composable MessageType (see App\Enums\MessageType::composable()) rather
 * than a fixed list per type. Each definition resolves against either
 * recipient source; a resolver returning null means the token cannot be
 * filled for that recipient — see App\Services\MessagePlaceholders.
 */
class PlaceholderRegistry
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    /**
     * @return array<int, array{token: string, resolveEmployee: callable(Employee): ?string, resolveUser: callable(User): ?string, sample: string}>
     */
    public function definitions(): array
    {
        return [
            [
                'token' => ':name',
                'resolveEmployee' => fn (Employee $employee) => $employee->first_name,
                'resolveUser' => fn (User $user) => $user->name,
                'sample' => __('mailbox.preview.sample_name'),
            ],
            [
                'token' => ':link',
                'resolveEmployee' => fn (Employee $employee) => $this->links->linkFor($employee),
                'resolveUser' => fn (User $user) => $user->employee ? $this->links->linkFor($user->employee) : null,
                'sample' => url('/personal/EXAMPLE-TOKEN'),
            ],
        ];
    }
}
