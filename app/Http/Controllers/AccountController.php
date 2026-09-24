<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        return Inertia::render('Account/Show', [
            'hasPassword' => $request->user()->password !== null,
            'businessLines' => BusinessLine::query()->get(['id', 'abbreviation'])->all(),
        ]);
    }

    public function updateBusinessLine(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'business_line_id' => ['nullable', 'exists:business_lines,id'],
        ]);

        if ($user->business_line_id !== ($data['business_line_id'] ?? null)) {
            BusinessLine::where('responsible_user_id', $user->id)->update(['responsible_user_id' => null]);
        }

        $user->update($data);

        return back()->with('success', __('account.flash.business_line_saved'));
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => $user->password ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $request->input('password')]);

        return back()->with('success', __('account.flash.password_saved'));
    }

    public function linkEmployee(Request $request)
    {
        $user = $request->user();

        abort_unless($user->role === User::ROLE_MANAGER && $user->employee_id === null, 403);

        // The user account carries one name string. Split it on the first
        // space; a name with no space becomes the first name alone.
        $name = trim($user->name);
        $firstName = Str::contains($name, ' ') ? Str::before($name, ' ') : $name;
        $lastName = Str::contains($name, ' ') ? trim(Str::after($name, ' ')) : '';

        $employee = Employee::firstOrCreate(
            ['email' => $user->email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'weekly_hours' => Employee::DEFAULT_WEEKLY_HOURS,
            ],
        );

        abort_if($employee->archived_at !== null, 409, __('employees.error.archived'));

        abort_if($employee->user()->whereKeyNot($user->id)->exists(), 409);

        $user->update(['employee_id' => $employee->id]);

        return redirect('/account')->with('success', __('account.flash.employee_linked'));
    }
}
