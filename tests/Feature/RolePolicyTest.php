<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id]);
        $user->assignRole($role);
        return $user;
    }

    // --- Super Admin ---

    public function test_super_admin_can_do_everything(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $otherOrg = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $otherOrg->id]);

        $this->assertTrue($superAdmin->can('view', $otherOrg));
        $this->assertTrue($superAdmin->can('update', $otherOrg));
        $this->assertTrue($superAdmin->can('view', $branch));
        $this->assertTrue($superAdmin->can('delete', $branch));
    }

    // --- Org Admin ---

    public function test_org_admin_can_manage_own_organization(): void
    {
        $admin = $this->createUserWithRole('org_admin');

        $this->assertTrue($admin->can('update', $this->org));
        $this->assertTrue($admin->can('manageBilling', $this->org));
    }

    public function test_org_admin_can_manage_branches(): void
    {
        $admin = $this->createUserWithRole('org_admin');
        $branch = Branch::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($admin->can('view', $branch));
        $this->assertTrue($admin->can('create', Branch::class));
        $this->assertTrue($admin->can('update', $branch));
        $this->assertTrue($admin->can('delete', $branch));
    }

    public function test_org_admin_cannot_manage_other_org_branches(): void
    {
        $admin = $this->createUserWithRole('org_admin');
        $otherOrg = Organization::factory()->create();
        $branch = Branch::factory()->create(['organization_id' => $otherOrg->id]);

        $this->assertFalse($admin->can('view', $branch));
        $this->assertFalse($admin->can('update', $branch));
        $this->assertFalse($admin->can('delete', $branch));
    }

    // --- Supervisor ---

    public function test_supervisor_can_view_branches_but_not_manage(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $branch = Branch::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($supervisor->can('view', $branch));
        $this->assertFalse($supervisor->can('create', Branch::class));
        $this->assertFalse($supervisor->can('update', $branch));
        $this->assertFalse($supervisor->can('delete', $branch));
    }

    public function test_supervisor_cannot_update_organization(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');

        $this->assertFalse($supervisor->can('update', $this->org));
        $this->assertFalse($supervisor->can('manageBilling', $this->org));
    }

    // --- Agent ---

    public function test_agent_has_minimal_permissions(): void
    {
        $agent = $this->createUserWithRole('agent');
        $branch = Branch::factory()->create(['organization_id' => $this->org->id]);

        $this->assertFalse($agent->can('view', $branch));
        $this->assertFalse($agent->can('create', Branch::class));
        $this->assertFalse($agent->can('update', $branch));
        $this->assertFalse($agent->can('delete', $branch));
    }

    public function test_agent_can_view_own_organization(): void
    {
        $agent = $this->createUserWithRole('agent');

        $this->assertTrue($agent->can('view', $this->org));
        $this->assertFalse($agent->can('update', $this->org));
    }

    // --- Seeder ---

    public function test_roles_are_seeded_correctly(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
        $this->assertDatabaseHas('roles', ['name' => 'org_admin']);
        $this->assertDatabaseHas('roles', ['name' => 'supervisor']);
        $this->assertDatabaseHas('roles', ['name' => 'agent']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEquals(5, \Spatie\Permission\Models\Role::count());
    }
}
