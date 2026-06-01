<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login multi-cuenta — Fase 1: cambiar de negocio activo.
 */
class OrganizationSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $orgA;
    private Organization $orgB;
    private Organization $inactive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['name' => 'Agencia de Viajes', 'status' => 'active']);
        $this->orgB = Organization::factory()->create(['name' => 'Trámites Gob', 'status' => 'active']);
        $this->inactive = Organization::factory()->create(['name' => 'Negocio Suspendido', 'status' => 'suspended']);

        $this->owner = User::factory()->create(['organization_id' => $this->orgA->id]);
        $this->owner->assignRole('org_admin');

        // El dueño está vinculado a sus tres negocios.
        $this->owner->organizations()->attach([
            $this->orgA->id => ['is_owner' => true],
            $this->orgB->id => ['is_owner' => true],
            $this->inactive->id => ['is_owner' => true],
        ]);
    }

    public function test_owner_can_switch_to_another_active_business(): void
    {
        $response = $this->actingAs($this->owner)->post(route('organizations.switch', $this->orgB));

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals($this->orgB->id, $this->owner->fresh()->organization_id);
    }

    public function test_cannot_switch_to_business_not_linked(): void
    {
        $stranger = Organization::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->owner)->post(route('organizations.switch', $stranger));

        $response->assertForbidden();
        $this->assertEquals($this->orgA->id, $this->owner->fresh()->organization_id);
    }

    public function test_cannot_switch_to_inactive_business(): void
    {
        $response = $this->actingAs($this->owner)->post(route('organizations.switch', $this->inactive));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals($this->orgA->id, $this->owner->fresh()->organization_id);
    }

    public function test_user_organizations_relation_lists_linked_businesses(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->orgA->id, $this->orgB->id, $this->inactive->id],
            $this->owner->organizations()->pluck('organizations.id')->all()
        );
    }

    public function test_can_access_organization_helper(): void
    {
        $stranger = Organization::factory()->create(['status' => 'active']);

        $this->assertTrue($this->owner->canAccessOrganization($this->orgB->id));
        $this->assertFalse($this->owner->canAccessOrganization($stranger->id));
    }
}
