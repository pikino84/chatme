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

    public function test_health_redis_returns_ok(): void
    {
        $response = $this->getJson('/health/redis');

        $response->assertOk()
            ->assertJsonStructure(['status', 'response_time_ms'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_queue_returns_ok(): void
    {
        $response = $this->getJson('/health/queue');

        $response->assertOk()
            ->assertJsonStructure(['status', 'pending_jobs', 'failed_last_hour'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_endpoints_require_no_auth(): void
    {
        // No actingAs — unauthenticated
        $this->getJson('/health/app')->assertOk();
        $this->getJson('/health/db')->assertOk();
        $this->getJson('/health/redis')->assertOk();
        $this->getJson('/health/queue')->assertOk();
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
