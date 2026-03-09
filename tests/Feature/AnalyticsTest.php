<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationSlaLog;
use App\Models\Deal;
use App\Models\Message;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\PlansAndFeaturesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $otherOrg;
    private User $admin;
    private User $agent;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\Illuminate\Broadcasting\BroadcastEvent::class]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansAndFeaturesSeeder::class);

        $this->org = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'status' => 'active',
        ]);

        $this->otherOrg = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('org_admin');

        $this->agent = User::factory()->create(['organization_id' => $this->org->id]);
        $this->agent->assignRole('agent');

        $this->channel = Channel::factory()->create([
            'organization_id' => $this->org->id,
            'type' => 'whatsapp',
        ]);

        // Subscribe org to Professional plan (reports_enabled = true)
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

    public function test_admin_can_access_analytics_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('analytics.index'));

        $response->assertOk();
        $response->assertViewHas('conversations');
        $response->assertViewHas('messages');
        $response->assertViewHas('deals');
        $response->assertViewHas('agents');
        $response->assertViewHas('sla');
        $response->assertViewHas('conversationTrend');
    }

    public function test_agent_without_permission_cannot_access_analytics(): void
    {
        $response = $this->actingAs($this->agent)
            ->get(route('analytics.index'));

        $response->assertForbidden();
    }

    public function test_feature_gating_blocks_starter_plan(): void
    {
        // Switch to Starter plan (reports_enabled = false)
        $starterPlan = Plan::where('slug', 'starter')->first();
        OrganizationSubscription::where('organization_id', $this->org->id)->update([
            'plan_id' => $starterPlan->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('analytics.index'));

        $response->assertForbidden();
    }

    public function test_conversation_metrics_are_correct(): void
    {
        Conversation::factory()->count(3)->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'status' => 'open',
        ]);
        Conversation::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getConversationMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );

        $this->assertEquals(5, $metrics['total']);
        $this->assertEquals(3, $metrics['open']);
        $this->assertEquals(2, $metrics['closed']);
        $this->assertArrayHasKey('whatsapp', $metrics['by_channel']);
        $this->assertEquals(5, $metrics['by_channel']['whatsapp']);
    }

    public function test_message_metrics_are_correct(): void
    {
        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        Message::factory()->count(4)->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
        ]);
        Message::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getMessageMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );

        $this->assertEquals(6, $metrics['total']);
        $this->assertEquals(4, $metrics['inbound']);
        $this->assertEquals(2, $metrics['outbound']);
        $this->assertIsArray($metrics['daily_volume']);
    }

    public function test_deal_metrics_are_correct(): void
    {
        $pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $stage = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
        ]);

        Deal::factory()->count(3)->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'status' => 'open',
            'value' => 1000,
        ]);
        Deal::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'status' => 'won',
            'value' => 5000,
            'closed_at' => now(),
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getDealMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );

        $this->assertEquals(5, $metrics['total']);
        $this->assertEquals(3, $metrics['open']);
        $this->assertEquals(2, $metrics['won']);
        $this->assertEquals(13000, $metrics['total_value']);
        $this->assertEquals(10000, $metrics['won_value']);
        $this->assertEquals(40.0, $metrics['conversion_rate']);
    }

    public function test_tenant_isolation_in_analytics(): void
    {
        // Create data for main org
        Conversation::factory()->count(3)->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        // Create data for other org
        $otherChannel = Channel::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'type' => 'whatsapp',
        ]);
        Conversation::factory()->count(5)->create([
            'organization_id' => $this->otherOrg->id,
            'channel_id' => $otherChannel->id,
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getConversationMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );

        $this->assertEquals(3, $metrics['total']);
    }

    public function test_agent_metrics_returns_correct_data(): void
    {
        Conversation::factory()->count(3)->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'assigned_user_id' => $this->agent->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $service = app(AnalyticsService::class);
        $agents = $service->getAgentMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );

        $this->assertCount(1, $agents);
        $this->assertEquals($this->agent->name, $agents[0]['name']);
        $this->assertEquals(3, $agents[0]['total_conversations']);
        $this->assertEquals(3, $agents[0]['closed_conversations']);
    }

    public function test_sla_metrics_returns_correct_data(): void
    {
        $conversation = Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
        ]);

        ConversationSlaLog::factory()->count(8)->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $conversation->id,
            'breached' => false,
        ]);
        ConversationSlaLog::factory()->count(2)->create([
            'organization_id' => $this->org->id,
            'conversation_id' => $conversation->id,
            'breached' => true,
        ]);

        $service = app(AnalyticsService::class);
        $metrics = $service->getSlaMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );

        $this->assertEquals(10, $metrics['total']);
        $this->assertEquals(2, $metrics['breached']);
        $this->assertEquals(8, $metrics['compliant']);
        $this->assertEquals(80.0, $metrics['compliance_rate']);
    }

    public function test_csv_export_returns_download(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('analytics.export', ['period' => '7d']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_period_filter_works(): void
    {
        // Create a conversation 15 days ago
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_at' => now()->subDays(15),
        ]);
        // Create a conversation today
        Conversation::factory()->create([
            'organization_id' => $this->org->id,
            'channel_id' => $this->channel->id,
            'created_at' => now(),
        ]);

        $service = app(AnalyticsService::class);

        // 7d should only show 1
        $metrics7d = $service->getConversationMetrics(
            $this->org->id,
            now()->subDays(7)->startOfDay(),
            now()->endOfDay()
        );
        $this->assertEquals(1, $metrics7d['total']);

        // 30d should show 2
        $metrics30d = $service->getConversationMetrics(
            $this->org->id,
            now()->subDays(30)->startOfDay(),
            now()->endOfDay()
        );
        $this->assertEquals(2, $metrics30d['total']);
    }
}
