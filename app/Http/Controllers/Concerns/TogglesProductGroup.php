<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\ProductGroup;

trait TogglesProductGroup
{
    /** Mark that the employee prefers the product group. Idempotent. */
    protected function attachProductGroup(Employee $employee, ProductGroup $productGroup): void
    {
        $employee->productGroups()->syncWithoutDetaching([$productGroup->id]);
    }

    /** Clear the product group from the employee. A no-op if it was not set. */
    protected function detachProductGroup(Employee $employee, ProductGroup $productGroup): void
    {
        $employee->productGroups()->detach($productGroup->id);
    }
}
