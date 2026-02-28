<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('tenant')->get('/tenant-test', function () {
            return response()->json([
                'tenant' => app('tenant')->slug,
            ]);
        });
    }

    public function test_resolves_tenant_from_subdomain(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);

        $response = $this->get('http://acme.chatme.com.mx/tenant-test');

        $response->assertOk();
        $response->assertJson(['tenant' => 'acme']);
    }

    public function test_returns_404_for_unknown_tenant(): void
    {
        $response = $this->get('http://unknown.chatme.com.mx/tenant-test');

        $response->assertNotFound();
    }

    public function test_returns_403_for_suspended_tenant(): void
    {
        Organization::factory()->suspended()->create(['slug' => 'suspended-co']);

        $response = $this->get('http://suspended-co.chatme.com.mx/tenant-test');

        $response->assertForbidden();
    }

    public function test_tenant_is_bound_in_container(): void
    {
        $org = Organization::factory()->create(['slug' => 'testco']);

        $this->get('http://testco.chatme.com.mx/tenant-test');

        $this->assertInstanceOf(Organization::class, app('tenant'));
        $this->assertEquals('testco', app('tenant')->slug);
    }
}
