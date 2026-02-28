<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // --- Permission Matrix Validation ---

    public function test_all_expected_permissions_exist(): void
    {
        $expected = [
            'organization.view', 'organization.update', 'organization.manage-billing',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'conversations.view', 'conversations.view-all', 'conversations.create',
            'conversations.assign', 'conversations.transfer', 'conversations.close', 'conversations.reopen',
            'messages.view', 'messages.send', 'messages.delete', 'messages.internal-note',
            'channels.view', 'channels.manage',
            'sla.view', 'sla.manage',
            'reports.view', 'reports.export',
            'settings.view', 'settings.update',
        ];

        foreach ($expected as $perm) {
            $this->assertNotNull(
                Permission::findByName($perm),
                "Permission '{$perm}' is missing"
            );
        }
    }

    public function test_all_expected_roles_exist(): void
    {
        foreach (['super_admin', 'org_admin', 'supervisor', 'agent'] as $role) {
            $this->assertNotNull(
                Role::findByName($role),
                "Role '{$role}' is missing"
            );
        }
    }

    // --- Privilege Escalation Prevention ---

    public function test_agent_cannot_escalate_to_admin_permissions(): void
    {
        $org = Organization::factory()->create();
        $agent = User::factory()->create(['organization_id' => $org->id]);
        $agent->assignRole('agent');

        $adminOnlyPerms = [
            'organization.update', 'organization.manage-billing',
            'users.create', 'users.update', 'users.delete',
            'branches.create', 'branches.update', 'branches.delete',
            'conversations.assign', 'conversations.reopen',
            'messages.delete',
            'channels.manage',
            'sla.manage',
            'reports.export',
            'settings.view', 'settings.update',
        ];

        foreach ($adminOnlyPerms as $perm) {
            $this->assertFalse(
                $agent->hasPermissionTo($perm),
                "Agent should NOT have '{$perm}'"
            );
        }
    }

    public function test_supervisor_cannot_escalate_to_admin_permissions(): void
    {
        $org = Organization::factory()->create();
        $supervisor = User::factory()->create(['organization_id' => $org->id]);
        $supervisor->assignRole('supervisor');

        $adminOnlyPerms = [
            'organization.update', 'organization.manage-billing',
            'users.create', 'users.update', 'users.delete',
            'branches.create', 'branches.update', 'branches.delete',
            'messages.delete',
            'channels.manage',
            'sla.manage',
            'reports.export',
            'settings.view', 'settings.update',
        ];

        foreach ($adminOnlyPerms as $perm) {
            $this->assertFalse(
                $supervisor->hasPermissionTo($perm),
                "Supervisor should NOT have '{$perm}'"
            );
        }
    }

    // --- Cross-Tenant Isolation ---

    public function test_cross_tenant_branch_access_blocked_at_all_levels(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $admin = User::factory()->create(['organization_id' => $org1->id]);
        $admin->assignRole('org_admin');

        $branch = Branch::factory()->create(['organization_id' => $org2->id]);

        $this->assertFalse($admin->can('view', $branch));
        $this->assertFalse($admin->can('update', $branch));
        $this->assertFalse($admin->can('delete', $branch));
    }

    public function test_cross_tenant_user_access_blocked(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $admin = User::factory()->create(['organization_id' => $org1->id]);
        $admin->assignRole('org_admin');

        $outsider = User::factory()->create(['organization_id' => $org2->id]);

        $this->assertFalse($admin->can('view', $outsider));
        $this->assertFalse($admin->can('update', $outsider));
        $this->assertFalse($admin->can('delete', $outsider));
    }

    public function test_cross_tenant_organization_access_blocked(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $admin = User::factory()->create(['organization_id' => $org1->id]);
        $admin->assignRole('org_admin');

        $this->assertFalse($admin->can('view', $org2));
        $this->assertFalse($admin->can('update', $org2));
        $this->assertFalse($admin->can('manageBilling', $org2));
    }

    // --- Super Admin Bypass ---

    public function test_super_admin_bypasses_all_tenant_checks(): void
    {
        $org = Organization::factory()->create();
        $superAdmin = User::factory()->create(); // no org
        $superAdmin->assignRole('super_admin');

        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create(['organization_id' => $org->id]);

        $this->assertTrue($superAdmin->can('view', $org));
        $this->assertTrue($superAdmin->can('update', $org));
        $this->assertTrue($superAdmin->can('view', $branch));
        $this->assertTrue($superAdmin->can('delete', $branch));
        $this->assertTrue($superAdmin->can('view', $user));
        $this->assertTrue($superAdmin->can('delete', $user));
    }

    // --- Destructive Operations ---

    public function test_organization_delete_always_denied(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('org_admin');

        $this->assertFalse($admin->can('delete', $org));
    }

    // --- Null Organization Guard ---

    public function test_user_without_org_cannot_access_tenant_resources(): void
    {
        $org = Organization::factory()->create();
        $orphan = User::factory()->create(['organization_id' => null]);

        $branch = Branch::factory()->create(['organization_id' => $org->id]);

        $this->assertFalse($orphan->can('view', $branch));
        $this->assertFalse($orphan->can('view', $org));
    }
}
