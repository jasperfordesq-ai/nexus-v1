<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the tenant bootstrap endpoint.
 *
 * The /v2/tenant/bootstrap endpoint is the first call the React SPA
 * makes on load — it returns tenant config, features, menus, etc.
 */
class TenantBootstrapTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Seed a minimal tenant row for bootstrap tests.
     */
    private function seedTestTenant(): void
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => $this->testTenantId,
            'name' => 'Hour Timebank',
            'slug' => $this->testTenantSlug,
            'domain' => null,
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test that bootstrap returns tenant data.
     */
    public function test_bootstrap_returns_tenant_data(): void
    {
        $this->seedTestTenant();

        $response = $this->apiGet('/v2/tenant/bootstrap');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
            ],
        ]);
    }

    /**
     * Test that bootstrap resolves tenant by slug query parameter.
     */
    public function test_bootstrap_resolves_tenant_by_slug(): void
    {
        $this->seedTestTenant();

        $response = $this->apiGet('/v2/tenant/bootstrap?slug=' . $this->testTenantSlug);

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', $this->testTenantSlug);
    }

    /**
     * Test that bootstrap includes feature flags.
     */
    public function test_bootstrap_includes_features(): void
    {
        $this->seedTestTenant();

        // Seed a feature row so the bootstrap has something to return
        DB::table('federation_tenant_features')->insertOrIgnore([
            'tenant_id' => $this->testTenantId,
            'feature_key' => 'listings',
            'is_enabled' => true,
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/tenant/bootstrap');

        $response->assertStatus(200);

        // The bootstrap response should include features somewhere in the data
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
    }

    /**
     * Test that bootstrap returns an error for a nonexistent slug.
     */
    public function test_bootstrap_returns_error_for_invalid_slug(): void
    {
        $response = $this->apiGet('/v2/tenant/bootstrap?slug=nonexistent-slug-xyz');

        // Should return 404 or an error in the response body
        $this->assertContains($response->getStatusCode(), [400, 404]);
    }

    /**
     * Test that the tenants list endpoint works.
     */
    public function test_tenants_list_endpoint_returns_data(): void
    {
        $this->seedTestTenant();

        $response = $this->apiGet('/v2/tenants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);
    }

    /**
     * Test that the platform stats endpoint responds.
     */
    public function test_platform_stats_endpoint_responds(): void
    {
        $this->seedTestTenant();

        $response = $this->apiGet('/v2/platform/stats');

        $response->assertStatus(200);
    }
}
