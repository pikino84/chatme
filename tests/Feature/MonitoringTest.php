<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUsageMonthly;
use App\Models\Plan;
use App\Models\SaasAlert;
use App\Models\User;
use App\Services\PerformanceMonitorService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── PerformanceMonitorService: Failed Jobs ──

    public function test_failed_jobs_creates_alert_when_threshold_exceeded(): void
    {
        $this->insertFailedJobs(15);

        $service = new PerformanceMonitorService();
        $result = $service->checkFailedJobs();

        $this->assertTrue($result);
        $this->assertDatabaseHas('saas_alerts', [
            'title' => 'Failed Jobs Alert',
            'type' => 'critical',
            'is_active' => true,
            'created_by' => null,
        ]);
    }

    public function test_failed_jobs_does_not_duplicate_alert(): void
    {
        $this->insertFailedJobs(15);

        $service = new PerformanceMonitorService();
        $service->checkFailedJobs();
        $service->checkFailedJobs();

        $this->assertEquals(1, SaasAlert::where('title', 'Failed Jobs Alert')->count());
    }

    public function test_no_alert_when_below_threshold(): void
    {
        $this->insertFailedJobs(3);

        $service = new PerformanceMonitorService();
        $result = $service->checkFailedJobs();

        $this->assertFalse($result);
        $this->assertDatabaseMissing('saas_alerts', ['title' => 'Failed Jobs Alert']);
    }

    public function test_old_failed_jobs_not_counted(): void
    {
        // Insert old failed jobs (>1 hour ago)
        for ($i = 0; $i < 15; $i++) {
            \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
                'uuid' => Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Test',
                'failed_at' => now()->subHours(2),
            ]);
        }

        $service = new PerformanceMonitorService();
        $result = $service->checkFailedJobs();

        $this->assertFalse($result);
    }

    // ── PerformanceMonitorService: Usage Limits ──

    public function test_usage_limit_creates_alert_when_over_90_percent(): void
    {
        $org = Organization::factory()->create();
        $plan = Plan::factory()->create();

        $feature = \App\Models\PlanFeature::create([
            'code' => 'messages_per_month',
            'name' => 'Messages Per Month',
            'description' => 'Monthly message limit',
            'type' => 'limit',
        ]);
        \App\Models\PlanFeatureValue::create([
            'plan_id' => $plan->id,
            'plan_feature_id' => $feature->id,
            'value' => '100',
        ]);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        OrganizationUsageMonthly::create([
            'organization_id' => $org->id,
            'feature_code' => 'messages_per_month',
            'period' => now()->format('Y-m'),
            'usage' => 95,
        ]);

        $service = new PerformanceMonitorService();
        $alertCount = $service->checkUsageLimits();

        $this->assertEquals(1, $alertCount);
        $this->assertDatabaseHas('saas_alerts', [
            'organization_id' => $org->id,
            'type' => 'warning',
        ]);
    }

    public function test_usage_below_90_percent_no_alert(): void
    {
        $org = Organization::factory()->create();
        $plan = Plan::factory()->create();

        $feature = \App\Models\PlanFeature::create([
            'code' => 'messages_per_month',
            'name' => 'Messages Per Month',
            'description' => 'Monthly message limit',
            'type' => 'limit',
        ]);
        \App\Models\PlanFeatureValue::create([
            'plan_id' => $plan->id,
            'plan_feature_id' => $feature->id,
            'value' => '100',
        ]);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        OrganizationUsageMonthly::create([
            'organization_id' => $org->id,
            'feature_code' => 'messages_per_month',
            'period' => now()->format('Y-m'),
            'usage' => 50,
        ]);

        $service = new PerformanceMonitorService();
        $alertCount = $service->checkUsageLimits();

        $this->assertEquals(0, $alertCount);
    }

    // ── SaasAlert Model ──

    public function test_is_system_generated_returns_true_when_no_creator(): void
    {
        $alert = SaasAlert::create([
            'type' => 'warning',
            'title' => 'System Alert',
            'message' => 'Auto-generated',
            'is_active' => true,
            'created_by' => null,
        ]);

        $this->assertTrue($alert->isSystemGenerated());
    }

    public function test_is_system_generated_returns_false_when_creator_exists(): void
    {
        $user = User::factory()->create(['organization_id' => null]);

        $alert = SaasAlert::create([
            'type' => 'info',
            'title' => 'Manual Alert',
            'message' => 'Created by admin',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->assertFalse($alert->isSystemGenerated());
    }

    // ── Artisan Command ──

    public function test_artisan_monitor_command_runs(): void
    {
        $this->artisan('monitor:performance')
            ->assertExitCode(0);
    }

    // ── Horizon Gate ──

    public function test_horizon_gate_allows_saas_admin(): void
    {
        $admin = User::factory()->create(['organization_id' => null]);
        $admin->assignRole('saas_admin');

        $this->assertTrue(
            app(\Illuminate\Contracts\Auth\Access\Gate::class)
                ->forUser($admin)
                ->allows('viewHorizon')
        );
    }

    public function test_horizon_gate_blocks_regular_user(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('agent');

        $this->assertFalse(
            app(\Illuminate\Contracts\Auth\Access\Gate::class)
                ->forUser($user)
                ->allows('viewHorizon')
        );
    }

    public function test_horizon_gate_blocks_admin_with_org(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('saas_admin');

        $this->assertFalse(
            app(\Illuminate\Contracts\Auth\Access\Gate::class)
                ->forUser($user)
                ->allows('viewHorizon')
        );
    }

    // ── Run All Checks ──

    public function test_run_all_checks_returns_expected_keys(): void
    {
        $service = new PerformanceMonitorService();
        $results = $service->runAllChecks();

        $this->assertArrayHasKey('queue_backlog', $results);
        $this->assertArrayHasKey('failed_jobs', $results);
        $this->assertArrayHasKey('usage_limits', $results);
    }

    // ── Helper ──

    private function insertFailedJobs(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
                'uuid' => Str::uuid(),
                'connection' => 'redis',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Test exception',
                'failed_at' => now(),
            ]);
        }
    }
}
