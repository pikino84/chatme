<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        if (! $request->user()->can('users.view')) {
            abort(403);
        }

        $users = User::where('organization_id', app('tenant')->id)
            ->with('roles')
            ->orderBy('name')
            ->get();

        return view('settings.team', compact('users'));
    }

    public function updateRole(Request $request, User $user)
    {
        if (! $request->user()->can('users.update')) {
            abort(403);
        }

        if ($user->organization_id !== app('tenant')->id) {
            abort(403, 'User does not belong to this organization.');
        }

        $request->validate([
            'role' => 'required|in:org_admin,supervisor,agent',
        ]);

        if ($user->id === $request->user()->id && $request->input('role') !== 'org_admin') {
            return back()->with('error', 'You cannot demote yourself.');
        }

        $user->syncRoles([$request->input('role')]);

        return back()->with('success', "Role updated for {$user->name}.");
    }

    public function toggleActive(Request $request, User $user)
    {
        if (! $request->user()->can('users.update')) {
            abort(403);
        }

        if ($user->organization_id !== app('tenant')->id) {
            abort(403, 'User does not belong to this organization.');
        }

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$user->name} has been {$status}.");
    }
}
