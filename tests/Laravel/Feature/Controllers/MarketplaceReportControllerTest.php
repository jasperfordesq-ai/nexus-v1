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
 * Feature tests for MarketplaceReportController.
 *
 * Covers:
 *  - POST /v2/marketplace/listings/{id}/report — submit DSA report (auth required)
 *  - GET  /v2/admin/marketplace/listings/{id}/reports — admin-only view
 *  - Feature flag gating (marketplace must be enabled)
 *  - Validation errors (invalid reason)
 */
class MarketplaceReportControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function enableMarketplaceFeature(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['marketplace'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::setById($tenantId);
    }

    public function test_store_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiPost('/v2/marketplace/listings/1/report', [
            'reason' => 'counterfeit',
            'description' => 'Test report',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_store_returns_403_when_feature_disabled(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['marketplace' => false]),
        ]);
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/listings/1/report', [
            'reason' => 'counterfeit',
            'description' => 'Test report',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_reason(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/listings/1/report', [
            'reason' => 'not_a_valid_reason',
            'description' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_validates_description_required(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/listings/1/report', [
            'reason' => 'counterfeit',
        ]);

        $response->assertStatus(422);
    }

    public function test_index_requires_admin(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/marketplace/listings/1/reports');

        $response->assertStatus(403);
    }
}
