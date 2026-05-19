<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Smoke tests for MarketplaceOfferController.
 */
class MarketplaceOfferControllerTest extends TestCase
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
        $response = $this->apiPost('/v2/marketplace/listings/1/offers', []);
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_listForListing_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/listings/1/offers');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_sentOffers_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/my-offers/sent');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_receivedOffers_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/my-offers/received');
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_sentOffers_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/my-offers/sent');
        $this->assertLessThan(500, $response->status());
    }

    public function test_receivedOffers_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/marketplace/my-offers/received');
        $this->assertLessThan(500, $response->status());
    }

    public function test_store_rejects_cross_tenant_listing_before_email_or_bell(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $buyer = $this->authenticatedUser();
        $otherTenantId = 999;
        $seller = User::factory()->forTenant($otherTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $otherTenantId,
            'user_id' => $seller->id,
            'title' => 'Cross tenant listing',
            'description' => 'Must not accept offers from another tenant.',
            'price' => 12.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'contacts_count' => 0,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost("/v2/marketplace/listings/{$listingId}/offers", [
            'amount' => 12.00,
            'message' => 'Cross-tenant attempt',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('marketplace_offers', [
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyer->id,
        ]);
        $this->assertSame(0, DB::table('notifications')->where('tenant_id', $otherTenantId)->where('user_id', $seller->id)->count());
    }
}
