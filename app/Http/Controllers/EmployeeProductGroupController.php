<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TogglesProductGroup;
use App\Models\Employee;
use App\Models\ProductGroup;

class EmployeeProductGroupController extends Controller
{
    use TogglesProductGroup;

    public function update(Employee $employee, ProductGroup $productGroup)
    {
        $this->attachProductGroup($employee, $productGroup);

        return back();
    }

    public function destroy(Employee $employee, ProductGroup $productGroup)
    {
        $this->detachProductGroup($employee, $productGroup);

        return back();
    }
}
