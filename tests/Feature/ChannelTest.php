<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_channel_belongs_to_organization(): void
    {
        $org = Organization::factory()->create();
        $channel = Channel::factory()->create(['organization_id' => $org->id]);

        $this->assertEquals($org->id, $channel->organization->id);
    }

    public function test_channel_types(): void
    {
        $org = Organization::factory()->create();

        $wa = Channel::factory()->create(['organization_id' => $org->id]);
        $wc = Channel::factory()->webchat()->create(['organization_id' => $org->id]);
        $em = Channel::factory()->email()->create(['organization_id' => $org->id]);

        $this->assertEquals('whatsapp', $wa->type);
        $this->assertEquals('webchat', $wc->type);
        $this->assertEquals('email', $em->type);
    }

    public function test_channel_scoped_by_tenant(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        Channel::factory()->count(2)->create(['organization_id' => $org1->id]);
        Channel::factory()->count(3)->create(['organization_id' => $org2->id]);

        app()->instance('tenant', $org1);
        $this->assertCount(2, Channel::all());
    }

    public function test_org_admin_can_manage_channels(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('org_admin');
        $channel = Channel::factory()->create(['organization_id' => $org->id]);

        $this->assertTrue($admin->can('view', $channel));
        $this->assertTrue($admin->can('update', $channel));
        $this->assertTrue($admin->can('delete', $channel));
    }

    public function test_agent_cannot_manage_channels(): void
    {
        $org = Organization::factory()->create();
        $agent = User::factory()->create(['organization_id' => $org->id]);
        $agent->assignRole('agent');
        $channel = Channel::factory()->create(['organization_id' => $org->id]);

        $this->assertFalse($agent->can('view', $channel));
        $this->assertFalse($agent->can('update', $channel));
    }

    public function test_configuration_is_cast_to_array(): void
    {
        $channel = Channel::factory()->create([
            'configuration' => ['phone_number' => '+5215512345678', 'api_key' => 'test'],
        ]);

        $channel->refresh();
        $this->assertIsArray($channel->configuration);
        $this->assertEquals('+5215512345678', $channel->configuration['phone_number']);
    }
}
