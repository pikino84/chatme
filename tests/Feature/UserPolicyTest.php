<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
    }

    public function test_user_can_always_view_own_profile(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertTrue($agent->can('view', $agent));
    }

    public function test_user_can_always_update_own_profile(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertTrue($agent->can('update', $agent));
    }

    public function test_user_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');

        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_org_admin_can_view_users_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');
        $other = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($admin->can('view', $other));
    }

    public function test_org_admin_cannot_view_users_in_other_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');
        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->create(['organization_id' => $otherOrg->id]);

        $this->assertFalse($admin->can('view', $outsider));
    }

    public function test_org_admin_can_delete_user_in_same_org(): void
    {
        $admin = User::factory()->create(['organization_id' => $this->org->id]);
        $admin->assignRole('org_admin');
        $target = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($admin->can('delete', $target));
    }

    public function test_agent_cannot_view_other_users(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');
        $other = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertFalse($agent->can('view', $other));
    }

    public function test_agent_cannot_create_users(): void
    {
        $agent = User::factory()->create(['organization_id' => $this->org->id]);
        $agent->assignRole('agent');

        $this->assertFalse($agent->can('create', User::class));
    }

    public function test_supervisor_can_view_but_not_manage_users(): void
    {
        $supervisor = User::factory()->create(['organization_id' => $this->org->id]);
        $supervisor->assignRole('supervisor');
        $target = User::factory()->create(['organization_id' => $this->org->id]);

        $this->assertTrue($supervisor->can('view', $target));
        $this->assertFalse($supervisor->can('create', User::class));
        $this->assertFalse($supervisor->can('delete', $target));
    }
}
