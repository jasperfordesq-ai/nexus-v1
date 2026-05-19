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
 * Feature tests for MarketplacePaymentController.
 *
 * Focus on the controller-level guards that don't require Stripe:
 *  - Auth required on every endpoint
 *  - Feature flag gating
 *  - Validation (missing order_id, missing payment_intent_id)
 *  - 404 on unknown payment status lookup
 */
class MarketplacePaymentControllerTest extends TestCase
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

    public function test_create_intent_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiPost('/v2/marketplace/payments/create-intent', [
            'order_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_create_intent_returns_403_when_feature_disabled(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['marketplace' => false]),
        ]);
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/payments/create-intent', [
            'order_id' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_intent_validates_order_id(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/payments/create-intent', []);

        $response->assertStatus(422);
    }

    public function test_confirm_validates_payment_intent_id(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/payments/confirm', []);

        $response->assertStatus(422);
    }

    public function test_confirm_rejects_non_buyer_before_payment_mutation(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create();
        $listingId = $this->createListing($this->testTenantId, (int) $seller->id);
        $orderId = $this->createOrder($this->testTenantId, (int) $buyer->id, (int) $seller->id, $listingId, 'pi_non_buyer_guard');

        Sanctum::actingAs($otherUser);

        $response = $this->apiPost('/v2/marketplace/payments/confirm', [
            'payment_intent_id' => 'pi_non_buyer_guard',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('marketplace_payments', [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'stripe_payment_intent_id' => 'pi_non_buyer_guard',
        ]);
        $this->assertSame('pending_payment', DB::table('marketplace_orders')->where('id', $orderId)->value('status'));
    }

    public function test_confirm_rejects_unknown_local_payment_intent_before_stripe_lookup(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($buyer);

        $response = $this->apiPost('/v2/marketplace/payments/confirm', [
            'payment_intent_id' => 'pi_missing_local_order',
        ]);

        $response->assertStatus(404);
    }

    public function test_status_returns_404_for_unknown_payment(): void
    {
        if (! \Schema::hasTable('marketplace_payments')) {
            $this->markTestSkipped('marketplace_payments table not present in test DB.');
        }

        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/marketplace/payments/9999999/status');

        $response->assertStatus(404);
    }

    public function test_balance_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiGet('/v2/marketplace/seller/balance');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_payouts_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiGet('/v2/marketplace/seller/payouts');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    private function createListing(int $tenantId, int $sellerId): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $sellerId,
            'title' => 'Payment guard listing',
            'description' => 'A listing used to verify payment confirmation auth.',
            'price' => 15.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $tenantId, int $buyerId, int $sellerId, int $listingId, string $paymentIntentId): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $tenantId,
            'order_number' => 'MKT-PAY-' . uniqid(),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => $listingId,
            'marketplace_offer_id' => null,
            'quantity' => 1,
            'unit_price' => 15.00,
            'total_price' => 15.00,
            'currency' => 'EUR',
            'status' => 'pending_payment',
            'payment_intent_id' => $paymentIntentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
