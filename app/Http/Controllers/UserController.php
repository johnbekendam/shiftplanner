<?php

namespace App\Http\Controllers;

use App\Models\BusinessLine;
use App\Models\User;
use App\Services\Auth\LoginLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private LoginLinkService $links) {}

    public function index()
    {
        $users = User::query()->with('businessLine')->orderBy('name')->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'business_line' => $user->businessLine?->abbreviation,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);

        return Inertia::render('Users/Index', ['users' => $users]);
    }

    public function create()
    {
        return Inertia::render('Users/Form', ['user' => null, 'businessLines' => $this->businessLines()]);
    }

    public function store(Request $request)
    {
        $user = User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::ROLES)],
            'business_line_id' => ['nullable', 'exists:business_lines,id'],
        ]));

        $this->links->sendInvite($user);

        return redirect('/users')->with('success', __('users.flash.created'));
    }

    public function resendInvite(User $user)
    {
        $this->links->sendInvite($user);

        return back()->with('success', __('users.flash.invite_resent'));
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Form', [
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'business_line_id']),
            'businessLines' => $this->businessLines(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['required', 'boolean'],
            'business_line_id' => ['nullable', 'exists:business_lines,id'],
        ]);

        $losesAdmin = $data['role'] !== User::ROLE_ADMIN || $data['is_active'] === false;

        if ($user->isAdmin() && $losesAdmin && $this->noOtherActiveAdmin($user)) {
            throw ValidationException::withMessages(['role' => __('users.error.last_admin')]);
        }

        if ($user->business_line_id !== ($data['business_line_id'] ?? null)) {
            BusinessLine::where('responsible_user_id', $user->id)->update(['responsible_user_id' => null]);
        }

        $user->update($data);

        return redirect('/users')->with('success', __('users.flash.updated'));
    }

    private function businessLines(): array
    {
        return BusinessLine::query()->get(['id', 'abbreviation'])->all();
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
