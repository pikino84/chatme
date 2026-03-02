<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $orgAdmin;
    private User $agent;
    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->org = Organization::factory()->create();

        $this->orgAdmin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->orgAdmin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->domain = 'http://app.' . config('app.base_domain');

        app()->instance('tenant', $this->org);
        Event::fake();
    }

    private function settingsUrl(string $path = ''): string
    {
        return "{$this->domain}/settings{$path}";
    }

    public function test_org_admin_can_view_settings(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get($this->settingsUrl());

        $response->assertOk();
        $response->assertViewIs('settings.organization');
    }

    public function test_org_admin_can_update_org_name(): void
    {
        $response = $this->actingAs($this->orgAdmin)->post($this->settingsUrl(), [
            'name' => 'Updated Corp',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Corp', $this->org->fresh()->name);
    }

    public function test_org_admin_can_update_timezone(): void
    {
        $response = $this->actingAs($this->orgAdmin)->post($this->settingsUrl(), [
            'name' => $this->org->name,
            'timezone' => 'America/Mexico_City',
        ]);

        $response->assertRedirect();
        $this->assertEquals('America/Mexico_City', $this->org->fresh()->settings['timezone']);
    }

    public function test_org_admin_can_view_team(): void
    {
        $response = $this->actingAs($this->orgAdmin)->get($this->settingsUrl('/team'));

        $response->assertOk();
        $response->assertViewIs('settings.team');
        $response->assertViewHas('users');
    }

    public function test_org_admin_can_change_user_role(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post($this->settingsUrl("/team/{$this->agent->id}/role"), [
                'role' => 'supervisor',
            ]);

        $response->assertRedirect();
        $this->assertTrue($this->agent->fresh()->hasRole('supervisor'));
    }

    public function test_org_admin_can_deactivate_user(): void
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post($this->settingsUrl("/team/{$this->agent->id}/toggle"));

        $response->assertRedirect();
        $this->assertFalse($this->agent->fresh()->is_active);
    }

    public function test_agent_cannot_access_settings(): void
    {
        $response = $this->actingAs($this->agent)->get($this->settingsUrl());

        $response->assertForbidden();
    }

    public function test_cross_tenant_user_isolation(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->orgAdmin)
            ->post($this->settingsUrl("/team/{$otherUser->id}/toggle"));

        $response->assertForbidden();
    }
}
