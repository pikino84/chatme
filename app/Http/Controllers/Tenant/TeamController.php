<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Deal;
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

    public function store(Request $request)
    {
        if (! $request->user()->can('users.create')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:org_admin,supervisor,agent',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'organization_id' => app('tenant')->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        $this->auditService->logModelChange('user.created', $user, [], $request);

        return back()->with('success', "{$user->name} ha sido agregado como {$validated['role']}.");
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

    public function destroy(Request $request, User $user)
    {
        if (! $request->user()->can('users.delete')) {
            abort(403);
        }

        if ($user->organization_id !== app('tenant')->id) {
            abort(403, 'User does not belong to this organization.');
        }

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes eliminarte a ti mismo.');
        }

        $userName = $user->name;

        // Unassign deals so they can be reassigned later
        $dealsCount = Deal::withoutGlobalScopes()
            ->where('assigned_user_id', $user->id)
            ->update(['assigned_user_id' => null]);

        // Unassign conversations
        $convsCount = Conversation::withoutGlobalScopes()
            ->where('assigned_user_id', $user->id)
            ->update(['assigned_user_id' => null]);

        // Nullify user references in deal notes, attachments, commissions
        \App\Models\DealNote::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->update(['user_id' => null]);

        \App\Models\DealAttachment::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->update(['user_id' => null]);

        \App\Models\DealCommission::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->update(['user_id' => null]);

        $this->auditService->logModelChange('user.deleted', $user, [
            'deals_unassigned' => $dealsCount,
            'conversations_unassigned' => $convsCount,
        ], $request);

        $user->syncRoles([]);
        $user->delete();

        $message = "{$userName} ha sido eliminado.";
        if ($dealsCount > 0 || $convsCount > 0) {
            $parts = [];
            if ($dealsCount > 0) $parts[] = "{$dealsCount} negocio(s)";
            if ($convsCount > 0) $parts[] = "{$convsCount} conversación(es)";
            $message .= ' ' . implode(' y ', $parts) . ' quedaron sin asignar.';
        }

        return back()->with('success', $message);
    }
}
