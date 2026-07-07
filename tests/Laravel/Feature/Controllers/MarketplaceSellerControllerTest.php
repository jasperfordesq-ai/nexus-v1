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
use Illuminate\Support\Facades\Schema;
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

    private function ensureMarketplaceSellerSchema(): bool
    {
        return Schema::hasTable('marketplace_seller_profiles')
            && Schema::hasTable('marketplace_shipping_options');
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

    public function test_public_shipping_options_returns_active_options_for_seller_user_id(): void
    {
        if (!$this->ensureMarketplaceSellerSchema()) {
            $this->markTestSkipped('Marketplace seller shipping tables not present.');
        }

        $this->enableMarketplaceFeature();
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $now = now();

        $profileId = DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'display_name' => 'Shipping Seller',
            'seller_type' => 'private',
            'joined_marketplace_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('marketplace_shipping_options')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'seller_id' => $profileId,
                'courier_name' => 'Standard Post',
                'courier_code' => 'standard',
                'price' => 4.99,
                'currency' => 'EUR',
                'estimated_days' => 3,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->testTenantId,
                'seller_id' => $profileId,
                'courier_name' => 'Inactive Courier',
                'courier_code' => 'inactive',
                'price' => 9.99,
                'currency' => 'EUR',
                'estimated_days' => 1,
                'is_default' => false,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $response = $this->apiGet("/v2/marketplace/sellers/{$seller->id}/shipping-options");

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.courier_name', 'Standard Post');
    }
}
