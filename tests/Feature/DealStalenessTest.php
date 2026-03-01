<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\SaasAlert;
use App\Services\PerformanceMonitorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealStalenessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Pipeline $pipeline;
    private PipelineStage $stageWithSla;
    private PipelineStage $stageWithoutSla;
    private PerformanceMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->pipeline = Pipeline::factory()->create(['organization_id' => $this->org->id]);
        $this->stageWithSla = PipelineStage::factory()->withMaxDuration(24)->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Proposal',
            'position' => 2,
        ]);
        $this->stageWithoutSla = PipelineStage::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'New',
            'position' => 1,
            'max_duration_hours' => null,
        ]);
        $this->service = new PerformanceMonitorService();
    }

    public function test_stale_deal_creates_alert(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithSla->id,
            'stage_entered_at' => now()->subHours(48),
            'contact_name' => 'Stale Client',
            'status' => 'open',
        ]);

        $count = $this->service->checkDealStaleness();

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('saas_alerts', [
            'organization_id' => $this->org->id,
            'type' => 'warning',
        ]);
    }

    public function test_no_alert_below_threshold(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithSla->id,
            'stage_entered_at' => now()->subHours(12), // below 24h threshold
            'status' => 'open',
        ]);

        $count = $this->service->checkDealStaleness();

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('saas_alerts', 0);
    }

    public function test_closed_deals_skipped(): void
    {
        Deal::factory()->won()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithSla->id,
            'stage_entered_at' => now()->subHours(48),
        ]);

        $count = $this->service->checkDealStaleness();

        $this->assertEquals(0, $count);
    }

    public function test_deals_without_sla_skipped(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithoutSla->id,
            'stage_entered_at' => now()->subHours(100),
            'status' => 'open',
        ]);

        $count = $this->service->checkDealStaleness();

        $this->assertEquals(0, $count);
    }

    public function test_dedup_stale_alerts(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithSla->id,
            'stage_entered_at' => now()->subHours(48),
            'contact_name' => 'Dedup Client',
            'status' => 'open',
        ]);

        $this->service->checkDealStaleness();
        $this->service->checkDealStaleness();

        $alerts = SaasAlert::where('organization_id', $this->org->id)
            ->where('title', 'like', 'Stale Deal: Dedup Client%')
            ->count();

        $this->assertEquals(1, $alerts);
    }

    public function test_run_all_checks_includes_deal_staleness(): void
    {
        $results = $this->service->runAllChecks();

        $this->assertArrayHasKey('deal_staleness', $results);
    }

    public function test_multiple_stale_deals_create_separate_alerts(): void
    {
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithSla->id,
            'stage_entered_at' => now()->subHours(48),
            'contact_name' => 'Client A',
            'status' => 'open',
        ]);
        Deal::factory()->create([
            'organization_id' => $this->org->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWithSla->id,
            'stage_entered_at' => now()->subHours(72),
            'contact_name' => 'Client B',
            'status' => 'open',
        ]);

        $count = $this->service->checkDealStaleness();

        $this->assertEquals(2, $count);
    }
}
