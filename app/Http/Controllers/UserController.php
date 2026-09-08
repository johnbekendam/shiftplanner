<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()->orderBy('name')->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'has_password' => $user->password !== null,
        ]);

        return Inertia::render('Users/Index', ['users' => $users]);
    }

    public function create()
    {
        return Inertia::render('Users/Form', ['user' => null]);
    }

    public function store(Request $request)
    {
        User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
        ]));

        return redirect('/users')->with('success', __('users.flash.created'));
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Form', [
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        $losesAdmin = $data['role'] !== User::ROLE_ADMIN || $data['is_active'] === false;

        if ($user->isAdmin() && $losesAdmin && $this->noOtherActiveAdmin($user)) {
            throw ValidationException::withMessages(['role' => __('users.error.last_admin')]);
        }

        $user->update($data);

        return redirect('/users')->with('success', __('users.flash.updated'));
    }

    private function noOtherActiveAdmin(User $user): bool
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
