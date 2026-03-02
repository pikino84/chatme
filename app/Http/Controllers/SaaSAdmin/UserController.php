<?php

namespace App\Http\Controllers\SaaSAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['organization', 'roles']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($orgId = $request->input('organization_id')) {
            $query->where('organization_id', $orgId);
        }

        $users = $query->latest()->paginate(20)->withQueryString();
        $roles = Role::orderBy('name')->pluck('name');
        $organizations = Organization::orderBy('name')->get();

        return view('saas-admin.users.index', compact('users', 'roles', 'organizations'));
    }

    public function show(User $user)
    {
        $user->load(['organization', 'roles']);

        return view('saas-admin.users.show', compact('user'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->pluck('name');
        $organizations = Organization::orderBy('name')->get();

        return view('saas-admin.users.form', [
            'user' => null,
            'roles' => $roles,
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', Password::defaults()],
            'organization_id' => 'nullable|exists:organizations,id',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $validated['organization_id'],
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('saas-admin.users.show', $user)
            ->with('success', "User '{$user->name}' created.");
    }

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->pluck('name');
        $organizations = Organization::orderBy('name')->get();

        return view('saas-admin.users.form', compact('user', 'roles', 'organizations'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', Password::defaults()],
            'organization_id' => 'nullable|exists:organizations,id',
            'role' => 'required|exists:roles,name',
            'is_active' => 'nullable|boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'organization_id' => $validated['organization_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('saas-admin.users.show', $user)
            ->with('success', "User '{$user->name}' updated.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('saas-admin.users.index')
            ->with('success', 'User deleted.');
    }
}
