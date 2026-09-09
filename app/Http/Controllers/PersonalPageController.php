<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use App\Models\Employee;
use App\Models\ProductGroup;
use App\Models\Shift;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The employee personal page, reached by an opaque preview token.
 *
 * PROTOTYPE ONLY — no authentication. See EmployeePersonalLinkService and
 * roadmap phase 2. A missing token is a 404; the page never reveals
 * whether a token "could" exist.
 */
class PersonalPageController extends Controller
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    public function show(string $token)
    {
        $employee = $this->resolveOrFail($token);

        return Inertia::render('Personal/Show', [
            'token' => $token,
            'employee' => [
                'name' => $employee->name,
                'email' => $employee->email,
                'weekly_hours' => $employee->weekly_hours,
            ],
            'holidays' => $employee->holidays->map->toPayload()->all(),
            'shifts' => Shift::all()->map->toPayload()->all(),
            'availability' => $employee->recurringAvailabilities->map->toPayload()->all(),
            'competences' => Competence::all()->map->toPayload()->all(),
            'competenceIds' => $employee->competences->pluck('id')->all(),
            'productGroups' => ProductGroup::all()->map->toPayload()->all(),
            'productGroupIds' => $employee->productGroups->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, string $token)
    {
        $employee = $this->resolveOrFail($token);

        $employee->update($request->validate([
            'weekly_hours' => ['required', 'integer', Rule::in(Employee::WEEKLY_HOURS_OPTIONS)],
        ]));

        return redirect("/personal/{$token}")->with('success', __('personal.flash.saved'));
    }

    private function resolveOrFail(string $token): Employee
    {
        return $this->links->resolve($token) ?? abort(404);
    }
}
