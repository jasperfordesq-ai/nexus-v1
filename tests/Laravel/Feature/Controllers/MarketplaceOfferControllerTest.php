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
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\MarketplaceOfferService;

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

    public function test_store_rejects_unapproved_expired_and_inactive_seller_listings(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);
        $buyer = $this->authenticatedUser();

        $scenarios = [
            'unapproved' => [
                'seller_status' => 'active',
                'listing' => ['moderation_status' => 'pending'],
            ],
            'expired' => [
                'seller_status' => 'active',
                'listing' => ['expires_at' => now()->subMinute()],
            ],
            'inactive seller' => [
                'seller_status' => 'inactive',
                'listing' => [],
            ],
            'suspended seller profile' => [
                'seller_status' => 'active',
                'listing' => [],
                'suspended_profile' => true,
            ],
        ];

        foreach ($scenarios as $name => $scenario) {
            $seller = User::factory()->forTenant($this->testTenantId)->create([
                'status' => $scenario['seller_status'],
                'is_approved' => true,
            ]);
            $listingId = (int) DB::table('marketplace_listings')->insertGetId(array_merge([
                'tenant_id' => $this->testTenantId,
                'user_id' => $seller->id,
                'title' => "Offer boundary: {$name}",
                'description' => 'Offers must obey the public listing availability boundary.',
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
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ], $scenario['listing']));

            if ($scenario['suspended_profile'] ?? false) {
                DB::table('marketplace_seller_profiles')->insert([
                    'tenant_id' => $this->testTenantId,
                    'user_id' => $seller->id,
                    'seller_type' => 'private',
                    'is_suspended' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $response = $this->apiPost("/v2/marketplace/listings/{$listingId}/offers", [
                'amount' => 10.00,
            ]);

            $response->assertStatus(422);
            $this->assertDatabaseMissing('marketplace_offers', [
                'marketplace_listing_id' => $listingId,
                'buyer_id' => $buyer->id,
            ]);
            $this->assertSame(0, (int) DB::table('marketplace_listings')
                ->where('id', $listingId)
                ->value('contacts_count'));
        }
    }

    public function test_accept_rechecks_listing_expiry_before_reserving_it(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);
        $seller = $this->authenticatedUser();
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Expired during negotiation',
            'description' => 'The offer cannot reactivate an expired listing.',
            'price' => 12.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'contacts_count' => 0,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $offerId = (int) DB::table('marketplace_offers')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 10.00,
            'currency' => 'EUR',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPut("/v2/marketplace/offers/{$offerId}/accept");

        $response->assertStatus(422);
        $this->assertDatabaseHas('marketplace_offers', ['id' => $offerId, 'status' => 'pending']);
        $this->assertDatabaseHas('marketplace_listings', ['id' => $listingId, 'status' => 'active']);
    }

    public function test_duplicate_active_offer_retry_creates_one_row_and_one_contact(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);
        $buyer = $this->authenticatedUser();
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Retry-safe offer listing',
            'description' => 'Offer creation is serialized on the listing row.',
            'price' => 12.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'contacts_count' => 0,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = ['amount' => 10.00, 'message' => 'One active offer only'];
        $this->apiPost("/v2/marketplace/listings/{$listingId}/offers", $payload)->assertCreated();
        $this->apiPost("/v2/marketplace/listings/{$listingId}/offers", $payload)->assertStatus(422);

        $this->assertSame(1, DB::table('marketplace_offers')
            ->where('tenant_id', $this->testTenantId)
            ->where('marketplace_listing_id', $listingId)
            ->where('buyer_id', $buyer->id)
            ->count());
        $this->assertSame(1, (int) DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->value('contacts_count'));
    }

    public function test_stale_concurrent_accept_rechecks_locked_offer_and_cannot_double_accept(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);
        $seller = $this->authenticatedUser();
        $buyerA = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $buyerB = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Atomic accept listing',
            'description' => 'Only one stale offer instance may transition to accepted.',
            'price' => 15.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $offerIds = [];
        foreach ([$buyerA, $buyerB] as $buyer) {
            $offerIds[] = (int) DB::table('marketplace_offers')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'marketplace_listing_id' => $listingId,
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'amount' => 12.00,
                'currency' => 'EUR',
                'status' => 'pending',
                'expires_at' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $winner = MarketplaceOffer::withoutGlobalScopes()->findOrFail($offerIds[0]);
        $staleLoser = MarketplaceOffer::withoutGlobalScopes()->findOrFail($offerIds[1]);

        MarketplaceOfferService::accept($winner, (int) $seller->id);
        try {
            MarketplaceOfferService::accept($staleLoser, (int) $seller->id);
            $this->fail('A stale offer must be rechecked after acquiring its row lock.');
        } catch (\InvalidArgumentException) {
            // Expected: the locked row was declined by the winning transition.
        }

        $this->assertDatabaseHas('marketplace_offers', ['id' => $offerIds[0], 'status' => 'accepted']);
        $this->assertDatabaseHas('marketplace_offers', ['id' => $offerIds[1], 'status' => 'declined']);
        $this->assertSame(1, DB::table('marketplace_offers')
            ->where('marketplace_listing_id', $listingId)
            ->where('status', 'accepted')
            ->count());
        $this->assertDatabaseHas('marketplace_listings', ['id' => $listingId, 'status' => 'reserved']);
    }
}
