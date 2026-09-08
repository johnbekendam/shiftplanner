<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeePersonalLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct(private EmployeePersonalLinkService $links) {}

    public function index(Request $request)
    {
        $search = $request->input('search', '');

        $query = Employee::query()->with('personalLink')->orderBy('name');

        if ($search !== '') {
            $query->search($search);
        }

        $employees = $query->paginate(20)->withQueryString()->through(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'weekly_hours' => $employee->weekly_hours,
            'has_personal_link' => $employee->personalLink !== null,
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    public function create()
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
        ]);
    }

    public function store(Request $request)
    {
        Employee::create($this->validated($request));

        return redirect('/employees')->with('success', __('employees.flash.created'));
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('Employees/Form', [
            'employee' => $employee->only(['id', 'name', 'email', 'weekly_hours']),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));

        return redirect('/employees')->with('success', __('employees.flash.updated'));
    }

    public function personalPage(Employee $employee)
    {
        return response()->json(['url' => $this->links->linkFor($employee)]);
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'weekly_hours' => ['required', 'integer', Rule::in(Employee::WEEKLY_HOURS_OPTIONS)],
        ]);
    }
}
