<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\MarketplaceOffer;
use App\Models\User;
use App\Services\MarketplaceConfigurationService;
use App\Services\MarketplaceOrderService;
use App\Services\MarketplacePickupSlotService;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class MarketplaceAtomicCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['marketplace'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features, JSON_THROW_ON_ERROR),
        ]);
        TenantContext::setById($this->testTenantId);
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            true,
        );
    }

    /** @return array{seller:User,buyer:User,seller_profile_id:int} */
    private function parties(float $buyerBalance = 20.0): array
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'balance' => 0,
        ]);
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'balance' => $buyerBalance,
        ]);
        TenantContext::setById($this->testTenantId);

        $profileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'business',
            'display_name' => 'Atomic checkout seller',
            'is_suspended' => false,
            'stripe_account_id' => 'acct_atomic_checkout_' . $seller->id,
            'stripe_onboarding_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['seller' => $seller, 'buyer' => $buyer, 'seller_profile_id' => $profileId];
    }

    private function listing(int $sellerId, array $overrides = []): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $sellerId,
            'title' => 'Atomic checkout listing ' . uniqid(),
            'description' => 'Exercises checkout rollback boundaries.',
            'price' => 10.00,
            'time_credit_price' => null,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 3,
            'inventory_count' => 3,
            'shipping_available' => false,
            'local_pickup' => true,
            'delivery_method' => 'pickup',
            'seller_type' => 'business',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addWeek(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function fullSlot(int $sellerProfileId): int
    {
        $slotId = $this->availableSlot($sellerProfileId);
        DB::table('marketplace_pickup_slots')->where('id', $slotId)->update([
            'booked_count' => 1,
        ]);

        return $slotId;
    }

    private function availableSlot(int $sellerProfileId): int
    {
        $slot = MarketplacePickupSlotService::create($sellerProfileId, [
            'slot_start' => now()->addDay()->toDateTimeString(),
            'slot_end' => now()->addDay()->addHour()->toDateTimeString(),
            'capacity' => 1,
            'is_active' => true,
        ]);

        return (int) $slot->id;
    }

    private function coupon(int $sellerProfileId, string $code, float $discountValue = 10): int
    {
        return (int) DB::table('merchant_coupons')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'seller_id' => $sellerProfileId,
            'code' => $code,
            'title' => 'Atomic checkout coupon',
            'discount_type' => 'percent',
            'discount_value' => $discountValue,
            'max_uses_per_member' => 1,
            'status' => 'active',
            'applies_to' => 'all_listings',
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function acceptedOffer(int $listingId, int $buyerId, int $sellerId, float $amount = 8.0): MarketplaceOffer
    {
        $offer = new MarketplaceOffer();
        $offer->tenant_id = $this->testTenantId;
        $offer->marketplace_listing_id = $listingId;
        $offer->buyer_id = $buyerId;
        $offer->seller_id = $sellerId;
        $offer->amount = $amount;
        $offer->currency = 'EUR';
        $offer->status = 'accepted';
        $offer->accepted_at = now();
        $offer->expires_at = now()->addHour();
        $offer->save();

        return $offer;
    }

    public function test_direct_pickup_failure_rolls_back_order_inventory_and_coupon(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id);
        $slotId = $this->fullSlot($fixture['seller_profile_id']);
        $couponId = $this->coupon($fixture['seller_profile_id'], 'ATOMICDIRECT');

        try {
            MarketplaceOrderService::createDirectPurchase((int) $fixture['buyer']->id, $listingId, [
                'listing_id' => $listingId,
                'quantity' => 1,
                'idempotency_key' => 'atomic-direct-coupon-checkout',
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
                'pickup_slot_id' => $slotId,
                'coupon_code' => 'ATOMICDIRECT',
            ]);
            $this->fail('A full pickup slot must reject the checkout.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(__('api.marketplace_pickup_reservation_failed'), $exception->getMessage());
        }

        $this->assertDatabaseMissing('marketplace_orders', ['checkout_key' => hash('sha256', 'atomic-direct-coupon-checkout')]);
        $this->assertSame(3, (int) DB::table('marketplace_listings')->where('id', $listingId)->value('inventory_count'));
        $this->assertSame(0, (int) DB::table('merchant_coupons')->where('id', $couponId)->value('usage_count'));
        $this->assertSame(0, DB::table('merchant_coupon_redemptions')->where('coupon_id', $couponId)->count());
        $this->assertSame(0, DB::table('marketplace_pickup_reservations')->where('slot_id', $slotId)->count());
    }

    public function test_direct_service_rejects_missing_idempotency_key_before_writes(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id);

        $this->expectException(\InvalidArgumentException::class);
        try {
            MarketplaceOrderService::createDirectPurchase((int) $fixture['buyer']->id, $listingId, [
                'listing_id' => $listingId,
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
            ]);
        } finally {
            $this->assertDatabaseMissing('marketplace_orders', [
                'marketplace_listing_id' => $listingId,
                'buyer_id' => $fixture['buyer']->id,
            ]);
            $this->assertSame(3, (int) DB::table('marketplace_listings')->where('id', $listingId)->value('inventory_count'));
        }
    }

    public function test_zero_total_cash_checkout_settles_locally_when_stripe_is_disabled(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id);
        $couponId = $this->coupon($fixture['seller_profile_id'], 'ZEROTOTAL', 100);
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            false,
        );

        $order = MarketplaceOrderService::createDirectPurchase(
            (int) $fixture['buyer']->id,
            $listingId,
            [
                'listing_id' => $listingId,
                'quantity' => 1,
                'idempotency_key' => 'zero-total-cash-without-stripe',
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
                'coupon_code' => 'ZEROTOTAL',
            ],
        );

        $this->assertSame('paid', $order->status);
        $this->assertSame(0.0, (float) $order->total_price);
        $this->assertNull($order->payment_expires_at);
        $this->assertSame(2, (int) DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->value('inventory_count'));
        $this->assertSame(1, DB::table('merchant_coupon_redemptions')
            ->where('coupon_id', $couponId)
            ->where('order_id', $order->id)
            ->count());
    }

    public function test_direct_pickup_cannot_bypass_seller_scheduled_slots(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id);
        $this->availableSlot($fixture['seller_profile_id']);

        try {
            MarketplaceOrderService::createDirectPurchase((int) $fixture['buyer']->id, $listingId, [
                'listing_id' => $listingId,
                'idempotency_key' => 'direct-missing-required-pickup-slot',
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
            ]);
            $this->fail('Scheduled pickup must require a slot selection.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(__('api.marketplace_pickup_reservation_failed'), $exception->getMessage());
        }

        $this->assertDatabaseMissing('marketplace_orders', [
            'checkout_key' => hash('sha256', 'direct-missing-required-pickup-slot'),
        ]);
        $this->assertSame(3, (int) DB::table('marketplace_listings')->where('id', $listingId)->value('inventory_count'));
    }

    public function test_non_pickup_checkout_rejects_pickup_slot_id(): void
    {
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ALLOW_SHIPPING,
            true,
        );
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id, [
            'shipping_available' => true,
            'local_pickup' => false,
            'delivery_method' => 'shipping',
        ]);
        $slotId = $this->availableSlot($fixture['seller_profile_id']);
        $optionId = (int) DB::table('marketplace_shipping_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'seller_id' => $fixture['seller_profile_id'],
            'courier_name' => 'Shipping only',
            'courier_code' => 'SHIP',
            'price' => 2,
            'currency' => 'EUR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        try {
            MarketplaceOrderService::createDirectPurchase((int) $fixture['buyer']->id, $listingId, [
                'listing_id' => $listingId,
                'idempotency_key' => 'shipping-with-invalid-pickup-slot',
                'payment_method' => 'cash',
                'shipping_option_id' => $optionId,
                'pickup_slot_id' => $slotId,
            ]);
        } finally {
            $this->assertDatabaseMissing('marketplace_orders', [
                'checkout_key' => hash('sha256', 'shipping-with-invalid-pickup-slot'),
            ]);
            $this->assertSame(0, DB::table('marketplace_pickup_reservations')->where('slot_id', $slotId)->count());
        }
    }

    public function test_direct_pickup_failure_occurs_before_time_credit_wallet_settlement(): void
    {
        $fixture = $this->parties(20.0);
        $listingId = $this->listing((int) $fixture['seller']->id, [
            'price' => 0,
            'time_credit_price' => 2,
        ]);
        $slotId = $this->fullSlot($fixture['seller_profile_id']);

        try {
            MarketplaceOrderService::createDirectPurchase((int) $fixture['buyer']->id, $listingId, [
                'listing_id' => $listingId,
                'quantity' => 1,
                'idempotency_key' => 'atomic-direct-wallet-checkout',
                'payment_method' => 'time_credits',
                'shipping_method' => 'pickup',
                'pickup_slot_id' => $slotId,
            ]);
            $this->fail('A full pickup slot must reject the checkout.');
        } catch (\InvalidArgumentException) {
            // Expected.
        }

        $this->assertDatabaseMissing('marketplace_orders', ['checkout_key' => hash('sha256', 'atomic-direct-wallet-checkout')]);
        $this->assertSame(3, (int) DB::table('marketplace_listings')->where('id', $listingId)->value('inventory_count'));
        $this->assertSame(20.0, (float) DB::table('users')->where('id', $fixture['buyer']->id)->value('balance'));
        $this->assertSame(0.0, (float) DB::table('users')->where('id', $fixture['seller']->id)->value('balance'));
        $this->assertSame(0, DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'marketplace_purchase')
            ->count());
    }

    public function test_atomic_pickup_failure_returns_translated_422_without_persisting_order(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id);
        $slotId = $this->fullSlot($fixture['seller_profile_id']);
        Sanctum::actingAs($fixture['buyer'], ['*']);

        $response = $this->apiPost('/v2/marketplace/orders', [
            'listing_id' => $listingId,
            'quantity' => 1,
            'idempotency_key' => 'atomic-pickup-http-response',
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
            'pickup_slot_id' => $slotId,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0.message', __('api.marketplace_pickup_reservation_failed'));
        $this->assertDatabaseMissing('marketplace_orders', [
            'checkout_key' => hash('sha256', 'atomic-pickup-http-response'),
        ]);
        $this->assertSame(3, (int) DB::table('marketplace_listings')->where('id', $listingId)->value('inventory_count'));
    }

    public function test_accepted_offer_pickup_failure_rolls_back_order_inventory_and_coupon(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id, ['status' => 'reserved']);
        $slotId = $this->fullSlot($fixture['seller_profile_id']);
        $couponId = $this->coupon($fixture['seller_profile_id'], 'ATOMICOFFER');
        $offer = $this->acceptedOffer($listingId, (int) $fixture['buyer']->id, (int) $fixture['seller']->id);

        try {
            MarketplaceOrderService::createFromOffer($offer, [
                'listing_id' => $listingId,
                'offer_id' => $offer->id,
                'idempotency_key' => 'atomic-accepted-offer-checkout',
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
                'pickup_slot_id' => $slotId,
                'coupon_code' => 'ATOMICOFFER',
            ]);
            $this->fail('A full pickup slot must reject the accepted-offer checkout.');
        } catch (\InvalidArgumentException) {
            // Expected.
        }

        $this->assertDatabaseMissing('marketplace_orders', ['marketplace_offer_id' => $offer->id]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'status' => 'reserved',
            'inventory_count' => 3,
        ]);
        $this->assertSame(0, (int) DB::table('merchant_coupons')->where('id', $couponId)->value('usage_count'));
        $this->assertSame(0, DB::table('merchant_coupon_redemptions')->where('coupon_id', $couponId)->count());
    }

    public function test_accepted_offer_pickup_cannot_bypass_seller_scheduled_slots(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id, ['status' => 'reserved']);
        $this->availableSlot($fixture['seller_profile_id']);
        $offer = $this->acceptedOffer($listingId, (int) $fixture['buyer']->id, (int) $fixture['seller']->id);

        try {
            MarketplaceOrderService::createFromOffer($offer, [
                'listing_id' => $listingId,
                'offer_id' => $offer->id,
                'idempotency_key' => 'offer-missing-required-pickup-slot',
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
            ]);
            $this->fail('Accepted-offer pickup must require a slot selection.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(__('api.marketplace_pickup_reservation_failed'), $exception->getMessage());
        }

        $this->assertDatabaseMissing('marketplace_orders', ['marketplace_offer_id' => $offer->id]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'status' => 'reserved',
            'inventory_count' => 3,
        ]);
    }

    public function test_only_accepted_offer_buyer_can_read_reserved_listing_for_checkout(): void
    {
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id, ['status' => 'reserved']);
        $offer = $this->acceptedOffer(
            $listingId,
            (int) $fixture['buyer']->id,
            (int) $fixture['seller']->id,
            8.0,
        );
        $slotId = $this->availableSlot($fixture['seller_profile_id']);

        Sanctum::actingAs($fixture['buyer'], ['*']);
        $this->apiGet("/v2/marketplace/listings/{$listingId}?offer_id={$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $listingId)
            ->assertJsonPath('data.status', 'reserved');
        $this->apiGet("/v2/marketplace/listings/{$listingId}/pickup-slots?offer_id={$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $slotId);

        $outsider = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        TenantContext::setById($this->testTenantId);
        Sanctum::actingAs($outsider, ['*']);
        $this->apiGet("/v2/marketplace/listings/{$listingId}?offer_id={$offer->id}")
            ->assertNotFound();
        $this->apiGet("/v2/marketplace/listings/{$listingId}/pickup-slots?offer_id={$offer->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_accepted_offer_uses_server_shipping_price_and_persists_fulfilment(): void
    {
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ALLOW_SHIPPING,
            true,
        );
        $fixture = $this->parties();
        $listingId = $this->listing((int) $fixture['seller']->id, [
            'status' => 'reserved',
            'shipping_available' => true,
            'local_pickup' => false,
            'delivery_method' => 'shipping',
        ]);
        $optionId = (int) DB::table('marketplace_shipping_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'seller_id' => $fixture['seller_profile_id'],
            'courier_name' => 'Authoritative Courier',
            'courier_code' => 'AUTH',
            'price' => 7.00,
            'currency' => 'EUR',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $offer = $this->acceptedOffer($listingId, (int) $fixture['buyer']->id, (int) $fixture['seller']->id, 8.0);
        $payload = [
            'listing_id' => $listingId,
            'offer_id' => $offer->id,
            'idempotency_key' => 'accepted-offer-authoritative-shipping',
            'payment_method' => 'cash',
            'shipping_option_id' => $optionId,
            'shipping_cost' => 0,
            'delivery_address' => ['line1' => '1 Test Street', 'country' => 'IE'],
            'delivery_notes' => 'Leave with reception.',
        ];

        $order = MarketplaceOrderService::createFromOffer($offer, $payload);
        $replay = MarketplaceOrderService::createFromOffer($offer->fresh(), $payload);

        $this->assertSame($order->id, $replay->id);
        $this->assertSame($optionId, (int) $order->shipping_option_id);
        $this->assertSame('AUTH', $order->shipping_method);
        $this->assertSame(7.0, (float) $order->shipping_cost);
        $this->assertSame(15.0, (float) $order->total_price);
        $this->assertSame('Leave with reception.', $order->delivery_notes);
        $this->assertSame('1 Test Street', $order->delivery_address['line1']);
        $this->assertSame(1, DB::table('marketplace_orders')->where('marketplace_offer_id', $offer->id)->count());
    }

    public function test_direct_and_offer_checkout_recheck_all_governed_listing_policies(): void
    {
        $fixture = $this->parties();
        $cases = [
            'free' => [
                'config' => MarketplaceConfigurationService::CONFIG_ALLOW_FREE_ITEMS,
                'listing' => ['price_type' => 'free', 'price' => 0],
                'payment_method' => 'free',
                'shipping_method' => 'pickup',
                'message' => __('api.marketplace_free_items_disabled'),
            ],
            'shipping' => [
                'config' => MarketplaceConfigurationService::CONFIG_ALLOW_SHIPPING,
                'listing' => [
                    'shipping_available' => true,
                    'local_pickup' => false,
                    'delivery_method' => 'shipping',
                ],
                'payment_method' => 'cash',
                'shipping_method' => null,
                'message' => __('api.marketplace_shipping_disabled'),
            ],
            'community_delivery' => [
                'config' => MarketplaceConfigurationService::CONFIG_ALLOW_COMMUNITY_DELIVERY,
                'listing' => [
                    'local_pickup' => false,
                    'delivery_method' => 'community_delivery',
                ],
                'payment_method' => 'cash',
                'shipping_method' => 'community_delivery',
                'message' => __('api.marketplace_community_delivery_disabled'),
            ],
            'hybrid' => [
                'config' => MarketplaceConfigurationService::CONFIG_ALLOW_HYBRID_PRICING,
                'listing' => ['price' => 10, 'time_credit_price' => 2],
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
                'message' => __('api.marketplace_hybrid_pricing_disabled'),
            ],
        ];

        foreach ($cases as $name => $case) {
            MarketplaceConfigurationService::set($case['config'], false);

            $directListingId = $this->listing(
                (int) $fixture['seller']->id,
                $case['listing'],
            );
            $directPayload = [
                'listing_id' => $directListingId,
                'quantity' => 1,
                'idempotency_key' => "policy-direct-{$name}",
                'payment_method' => $case['payment_method'],
            ];
            if ($case['shipping_method'] !== null) {
                $directPayload['shipping_method'] = $case['shipping_method'];
            }

            try {
                MarketplaceOrderService::createDirectPurchase(
                    (int) $fixture['buyer']->id,
                    $directListingId,
                    $directPayload,
                );
                $this->fail("Direct checkout must reject disabled {$name} policy.");
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame($case['message'], $exception->getMessage());
            }

            $offerListingId = $this->listing(
                (int) $fixture['seller']->id,
                array_merge($case['listing'], ['status' => 'reserved']),
            );
            $offer = $this->acceptedOffer(
                $offerListingId,
                (int) $fixture['buyer']->id,
                (int) $fixture['seller']->id,
            );
            $offerPayload = [
                'listing_id' => $offerListingId,
                'offer_id' => $offer->id,
                'quantity' => 1,
                'idempotency_key' => "policy-offer-{$name}",
                'payment_method' => 'cash',
            ];
            if ($case['shipping_method'] !== null) {
                $offerPayload['shipping_method'] = $case['shipping_method'];
            }

            try {
                MarketplaceOrderService::createFromOffer($offer, $offerPayload);
                $this->fail("Offer checkout must reject disabled {$name} policy.");
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame($case['message'], $exception->getMessage());
            }

            $this->assertDatabaseMissing('marketplace_orders', [
                'marketplace_listing_id' => $directListingId,
            ]);
            $this->assertDatabaseMissing('marketplace_orders', [
                'marketplace_listing_id' => $offerListingId,
            ]);
            MarketplaceConfigurationService::set($case['config'], true);
        }
    }
}
