<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_app_returns_ok(): void
    {
        $response = $this->getJson('/health/app');

        $response->assertOk()
            ->assertJsonStructure(['status', 'service', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_db_returns_ok(): void
    {
        $response = $this->getJson('/health/db');

        $response->assertOk()
            ->assertJsonStructure(['status', 'connection', 'response_time_ms'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_redis_returns_status(): void
    {
        $response = $this->getJson('/health/redis');

        // Redis may or may not be running — both are valid responses
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure(['status']);
    }

    public function test_health_queue_returns_status(): void
    {
        $response = $this->getJson('/health/queue');

        // Queue check may degrade if Redis is down, but DB part should work
        $this->assertContains($response->status(), [200, 503]);
        if ($response->status() === 200) {
            $response->assertJsonStructure(['status', 'pending_jobs', 'failed_last_hour']);
        }
    }

    public function test_health_endpoints_require_no_auth(): void
    {
        // No actingAs — unauthenticated
        $this->getJson('/health/app')->assertOk();
        $this->getJson('/health/db')->assertOk();
        // Redis/queue may be down, just verify no 401/403
        $redis = $this->getJson('/health/redis');
        $this->assertNotEquals(401, $redis->status());
        $this->assertNotEquals(403, $redis->status());
    }

    public function test_health_app_includes_service_name(): void
    {
        $response = $this->getJson('/health/app');

        $response->assertOk()
            ->assertJson(['service' => config('app.name')]);
    }

    public function test_health_db_includes_connection_type(): void
    {
        $response = $this->getJson('/health/db');

        $response->assertOk()
            ->assertJson(['connection' => config('database.default')]);
    }
}
