<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesProductGroup;
use App\Models\Employee;
use App\Models\ProductGroup;
use App\Services\EmployeePersonalLinkService;

/**
 * Employee preferred product groups from the personal page.
 *
 * PROTOTYPE ONLY — token-only, no authentication. See
 * EmployeePersonalLinkService and roadmap phase 2.
 */
class PersonalProductGroupController extends Controller
{
    use TogglesProductGroup;

    public function __construct(private EmployeePersonalLinkService $links) {}

    public function update(string $token, ProductGroup $productGroup)
    {
        $this->attachProductGroup($this->resolveOrFail($token), $productGroup);

        return redirect("/personal/{$token}");
    }

    public function destroy(string $token, ProductGroup $productGroup)
    {
        $this->detachProductGroup($this->resolveOrFail($token), $productGroup);

        return redirect("/personal/{$token}");
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
