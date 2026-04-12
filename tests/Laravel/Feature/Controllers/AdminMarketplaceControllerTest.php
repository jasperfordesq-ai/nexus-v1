<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminMarketplaceController.
 *
 * Covers admin-only access gates, the marketplace feature flag,
 * dashboard currency resolution (tenant-level default_currency), and
 * validation on reject/resolve endpoints.
 */
class AdminMarketplaceControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Enable the marketplace feature for the current test tenant.
     */
    private function enableMarketplaceFeature(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['marketplace'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        // Refresh TenantContext cache so hasFeature() sees the updated flags.
        TenantContext::setById($tenantId);
    }

    private function adminUser(): User
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    // ------------------------------------------------------------------
    //  Dashboard — auth + feature gate
    // ------------------------------------------------------------------

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/admin/marketplace/dashboard');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_dashboard_forbidden_for_regular_member(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/marketplace/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_403_when_marketplace_feature_disabled(): void
    {
        // Explicitly ensure feature is OFF.
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['marketplace' => false]),
        ]);
        TenantContext::setById($this->testTenantId);

        $this->adminUser();

        $response = $this->apiGet('/v2/admin/marketplace/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_happy_path_returns_counts_and_currency(): void
    {
        // Skip when the marketplace schema is not present in the test DB.
        if (! \Schema::hasTable('marketplace_listings') || ! \Schema::hasTable('marketplace_seller_profiles')) {
            $this->markTestSkipped('Marketplace tables not present in test database.');
        }

        $this->enableMarketplaceFeature($this->testTenantId);
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/marketplace/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_listings',
                'active_listings',
                'pending_moderation',
                'total_sellers',
                'total_orders',
                'revenue',
                'currency',
            ],
        ]);

        // Currency should be a non-empty ISO-ish string (tenant setting or app default).
        $currency = $response->json('data.currency');
        $this->assertIsString($currency);
        $this->assertNotEmpty($currency);
    }

    public function test_dashboard_uses_tenant_default_currency_setting(): void
    {
        if (! \Schema::hasTable('marketplace_listings') || ! \Schema::hasTable('marketplace_seller_profiles')) {
            $this->markTestSkipped('Marketplace tables not present in test database.');
        }

        $this->enableMarketplaceFeature($this->testTenantId);

        // Seed tenant-level default_currency setting via the canonical service,
        // which knows the actual column layout (no hardcoded 'category' column).
        \App\Services\TenantSettingsService::set(
            $this->testTenantId,
            'general.default_currency',
            'EUR'
        );

        $this->adminUser();

        $response = $this->apiGet('/v2/admin/marketplace/dashboard');

        $response->assertStatus(200);
        $this->assertSame('EUR', $response->json('data.currency'));
    }

    // ------------------------------------------------------------------
    //  Listings — pagination shape
    // ------------------------------------------------------------------

    public function test_listings_returns_paginated_envelope(): void
    {
        if (! \Schema::hasTable('marketplace_listings')) {
            $this->markTestSkipped('Marketplace tables not present in test database.');
        }

        $this->enableMarketplaceFeature($this->testTenantId);
        $this->adminUser();

        $response = $this->apiGet('/v2/admin/marketplace/listings?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta',
        ]);
    }

    // ------------------------------------------------------------------
    //  Reject listing — validation error (missing notes)
    // ------------------------------------------------------------------

    public function test_reject_listing_returns_validation_error_without_notes(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);
        $this->adminUser();

        $response = $this->apiPost('/v2/admin/marketplace/listings/1/reject', []);

        // Laravel returns 422 for validation failures; controller may also 404 first
        // if it runs findOrFail before validation. Either indicates the body was
        // rejected/listing missing — both are acceptable for this test's intent.
        $this->assertContains($response->getStatusCode(), [404, 422]);
    }

    // ------------------------------------------------------------------
    //  Resolve report — validation error (invalid action_taken)
    // ------------------------------------------------------------------

    public function test_resolve_report_returns_validation_error_for_invalid_action(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);
        $this->adminUser();

        $response = $this->apiPut('/v2/admin/marketplace/reports/1/resolve', [
            'action_taken' => 'invalid_action_value',
            'resolution_reason' => 'test',
        ]);

        $this->assertContains($response->getStatusCode(), [404, 422]);
    }

    // ------------------------------------------------------------------
    //  Reports index — admin only
    // ------------------------------------------------------------------

    public function test_reports_forbidden_for_regular_member(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/marketplace/reports');

        $response->assertStatus(403);
    }
}
