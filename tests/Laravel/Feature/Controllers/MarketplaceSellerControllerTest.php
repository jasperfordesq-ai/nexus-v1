<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Smoke tests for MarketplaceSellerController.
 */
class MarketplaceSellerControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function enableMarketplaceFeature(): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first(['features']);
        $features = json_decode((string) ($tenant->features ?? '{}'), true) ?: [];
        $features['marketplace'] = true;

        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);

        TenantContext::setById($this->testTenantId);
    }

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/seller/dashboard');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_onboardStatus_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/seller/onboard/status');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_shippingOptions_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/seller/shipping-options');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_onboard_status_returns_connect_capability_flags(): void
    {
        $this->enableMarketplaceFeature();
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/marketplace/seller/onboard/status');

        $response->assertSuccessful();
        $response->assertJsonPath('data.stripe_onboarding_complete', false);
        $response->assertJsonPath('data.details_submitted', false);
        $response->assertJsonPath('data.charges_enabled', false);
        $response->assertJsonPath('data.payouts_enabled', false);
    }

    public function test_dashboard_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/seller/dashboard');
        $this->assertLessThan(500, $response->status());
    }

    public function test_show_public_smoke(): void
    {
        $response = $this->apiGet('/v2/marketplace/sellers/1');
        $this->assertLessThan(500, $response->status());
    }

    public function test_listings_public_smoke(): void
    {
        $response = $this->apiGet('/v2/marketplace/sellers/1/listings');
        $this->assertLessThan(500, $response->status());
    }
}
