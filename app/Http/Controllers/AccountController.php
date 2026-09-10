<?php

namespace App\Http\Controllers;

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
        ]);
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

        abort_if($employee->user()->whereKeyNot($user->id)->exists(), 409);

        $user->update(['employee_id' => $employee->id]);

        return redirect('/account')->with('success', __('account.flash.employee_linked'));
    }
}
