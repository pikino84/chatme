<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\Message;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\SaasAlert;
use App\Models\User;
use App\Services\AutomationService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $agent1;
    private User $agent2;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org', 'status' => 'active']);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent1 = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $this->agent1->assignRole('agent');

        $this->agent2 = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $this->agent2->assignRole('agent');

        $this->channel = Channel::factory()->create(['organization_id' => $this->org->id, 'type' => 'whatsapp']);

        $plan = Plan::where('slug', 'professional')->first();
        OrganizationSubscription::create([
            'organization_id' => $this->org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        app()->instance('tenant', $this->org);
    }

    public function test_admin_can_view_automations(): void
    {
        $response = $this->actingAs($this->admin)->get(route('automations.index'));
        $response->assertOk();
    }

    public function test_feature_gating_blocks_starter_plan(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        OrganizationSubscription::where('organization_id', $this->org->id)->update(['plan_id' => $starterPlan->id]);

        $response = $this->actingAs($this->admin)->get(route('automations.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_create_auto_assign_rule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('automations.store'), [
            'name' => 'Auto Assign RR',
            'type' => 'auto_assign',
            'config' => ['method' => 'round_robin'],
        ]);

        $response->assertRedirect(route('automations.index'));
        $this->assertDatabaseHas('automation_rules', [
            'organization_id' => $this->org->id,
            'type' => 'auto_assign',
            'name' => 'Auto Assign RR',
        ]);
    }

    public function test_admin_can_create_auto_response_rule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('automations.store'), [
            'name' => 'Welcome Message',
            'type' => 'auto_response',
            'config' => ['message' => 'Gracias por contactarnos!'],
        ]);

        $response->assertRedirect(route('automations.index'));
        $this->assertDatabaseHas('automation_rules', [
            'organization_id' => $this->org->id,
            'type' => 'auto_response',
        ]);
    }

    public function test_admin_can_create_stale_alert_rule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('automations.store'), [
            'name' => 'Stale Alert 30m',
            'type' => 'stale_alert',
            'config' => ['threshold_minutes' => 30],
        ]);

        $response->assertRedirect(route('automations.index'));
        $this->assertDatabaseHas('automation_rules', [
            'type' => 'stale_alert',
        ]);
    }

    public function test_auto_assign_round_robin(): void
    {
        AutomationRule::factory()->autoAssign('round_robin')->create([
            'organization_id' => $this->org->id,
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => null,
        ]);

        $service = app(AutomationService::class);
        $service->handleNewConversation($conversation);

        $conversation->refresh();
        $this->assertNotNull($conversation->assigned_user_id);
        $this->assertDatabaseHas('conversation_assignments', [
            'conversation_id' => $conversation->id,
            'assigned_by' => null, // system assignment
        ]);
    }

    public function test_auto_assign_skips_already_assigned(): void
    {
        AutomationRule::factory()->autoAssign()->create([
            'organization_id' => $this->org->id,
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $this->agent1->id,
        ]);

        $service = app(AutomationService::class);
        $service->handleNewConversation($conversation);

        $conversation->refresh();
        $this->assertEquals($this->agent1->id, $conversation->assigned_user_id);
        $this->assertDatabaseMissing('conversation_assignments', [
            'conversation_id' => $conversation->id,
        ]);
    }

    public function test_auto_response_creates_message(): void
    {
        AutomationRule::factory()->autoResponse('Bienvenido!')->create([
            'organization_id' => $this->org->id,
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $service = app(AutomationService::class);
        $service->handleNewConversation($conversation);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Bienvenido!',
            'direction' => 'outbound',
        ]);
    }

    public function test_stale_alert_creates_alert(): void
    {
        AutomationRule::factory()->staleAlert(30)->create([
            'organization_id' => $this->org->id,
        ]);

        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'status' => 'open',
            'assigned_user_id' => null,
            'last_message_at' => now()->subMinutes(60),
        ]);

        $service = app(AutomationService::class);
        $alertCount = $service->checkStaleConversations();

        $this->assertEquals(1, $alertCount);
        $this->assertDatabaseHas('saas_alerts', [
            'organization_id' => $this->org->id,
            'type' => 'warning',
            'title' => 'Conversación sin atender',
        ]);
    }

    public function test_stale_alert_no_duplicate(): void
    {
        AutomationRule::factory()->staleAlert(30)->create([
            'organization_id' => $this->org->id,
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'status' => 'open',
            'assigned_user_id' => null,
            'last_message_at' => now()->subMinutes(60),
        ]);

        $service = app(AutomationService::class);
        $service->checkStaleConversations();
        $secondRun = $service->checkStaleConversations();

        $this->assertEquals(0, $secondRun);
    }

    public function test_inactive_rule_is_ignored(): void
    {
        AutomationRule::factory()->autoResponse('Should not appear')->inactive()->create([
            'organization_id' => $this->org->id,
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        $service = app(AutomationService::class);
        $service->handleNewConversation($conversation);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Should not appear',
        ]);
    }

    public function test_channel_specific_rule(): void
    {
        $otherChannel = Channel::factory()->create(['organization_id' => $this->org->id, 'type' => 'webchat']);

        AutomationRule::factory()->autoResponse('WhatsApp only')->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        // Conversation on different channel — rule should not fire
        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $otherChannel->id,
        ]);

        $service = app(AutomationService::class);
        $service->handleNewConversation($conversation);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'WhatsApp only',
        ]);
    }

    public function test_toggle_active(): void
    {
        $rule = AutomationRule::factory()->autoAssign()->create([
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('automations.toggle', $rule));

        $response->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_admin_can_delete_rule(): void
    {
        $rule = AutomationRule::factory()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('automations.destroy', $rule));

        $response->assertRedirect(route('automations.index'));
        $this->assertDatabaseMissing('automation_rules', ['id' => $rule->id]);
    }

    public function test_tenant_isolation(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $otherRule = AutomationRule::factory()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($this->admin)->get(route('automations.edit', $otherRule));
        $response->assertNotFound();
    }

    public function test_agent_cannot_create_rules(): void
    {
        $response = $this->actingAs($this->agent1)->post(route('automations.store'), [
            'name' => 'Test',
            'type' => 'auto_assign',
            'config' => ['method' => 'round_robin'],
        ]);

        $response->assertForbidden();
    }
}
