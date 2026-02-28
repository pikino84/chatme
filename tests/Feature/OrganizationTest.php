<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_created(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'active',
        ]);
    }

    public function test_organization_slug_is_unique(): void
    {
        Organization::factory()->create(['slug' => 'acme']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Organization::factory()->create(['slug' => 'acme']);
    }

    public function test_user_belongs_to_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        $this->assertEquals($org->id, $user->organization->id);
        $this->assertTrue($org->users->contains($user));
    }

    public function test_organization_has_status_helpers(): void
    {
        $active = Organization::factory()->create(['status' => 'active']);
        $suspended = Organization::factory()->suspended()->create();

        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isSuspended());
        $this->assertTrue($suspended->isSuspended());
        $this->assertFalse($suspended->isActive());
    }

    public function test_user_can_view_own_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        $this->assertTrue($user->can('view', $org));
    }

    public function test_user_cannot_view_other_organization(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org1->id]);

        $this->assertFalse($user->can('view', $org2));
    }

    public function test_settings_is_cast_to_array(): void
    {
        $org = Organization::factory()->create([
            'settings' => ['timezone' => 'America/Mexico_City', 'locale' => 'es'],
        ]);

        $org->refresh();
        $this->assertIsArray($org->settings);
        $this->assertEquals('America/Mexico_City', $org->settings['timezone']);
    }
}
