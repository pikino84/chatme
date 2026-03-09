<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private AuditService $auditService)
    {
    }

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

        $oldRole = $user->getRoleNames()->first();
        $user->syncRoles([$request->input('role')]);

        $this->auditService->logModelChange(
            'user.role_changed',
            $user,
            ['role' => $oldRole],
            $request,
        );

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

        $oldActive = $user->is_active;
        $user->update(['is_active' => ! $user->is_active]);

        $this->auditService->logModelChange(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            ['is_active' => $oldActive],
            $request,
        );

        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$user->name} has been {$status}.");
    }
}
