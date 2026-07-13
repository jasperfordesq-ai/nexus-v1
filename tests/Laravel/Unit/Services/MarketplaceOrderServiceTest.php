<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Core\TenantContext;
use App\Services\MarketplaceOrderService;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSellerProfile;
use App\Models\User;
use App\Services\MarketplaceConfigurationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;

class MarketplaceOrderServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            true,
        );
    }

    private function makePersistedOrder(string $status, array $attributes = []): MarketplaceOrder
    {
        $id = DB::table('marketplace_orders')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'order_number' => MarketplaceOrderService::generateOrderNumber($this->testTenantId),
            'buyer_id' => 910001,
            'seller_id' => 910002,
            'marketplace_listing_id' => null,
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return MarketplaceOrder::withoutGlobalScopes()->findOrFail($id);
    }

    // -----------------------------------------------------------------
    //  createFromOffer — guard clause
    // -----------------------------------------------------------------

    public function test_createFromOffer_throwsWhenOfferNotAccepted(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offer must be accepted before creating an order');

        MarketplaceOrderService::createFromOffer($offer, []);
    }

    public function test_createFromOffer_throwsWhenOfferIsDeclined(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->status = 'declined';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offer must be accepted');

        MarketplaceOrderService::createFromOffer($offer, []);
    }

    public function test_createFromOffer_rejects_expired_accepted_offer(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->status = 'accepted';
        $offer->expires_at = now()->subSecond();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('api.marketplace_offer_expired'));

        MarketplaceOrderService::createFromOffer($offer, []);
    }

    public function test_positive_cash_checkout_rejects_when_tenant_stripe_is_disabled(): void
    {
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_STRIPE_ENABLED,
            false,
        );
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Stripe-disabled cash item',
            'description' => 'Positive cash checkout must respect tenant configuration.',
            'price' => 10,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'inventory_count' => 1,
            'quantity' => 1,
            'local_pickup' => true,
            'delivery_method' => 'pickup',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            MarketplaceOrderService::createDirectPurchase((int) $buyer->id, $listingId, [
                'listing_id' => $listingId,
                'idempotency_key' => 'stripe-disabled-checkout',
                'payment_method' => 'cash',
                'shipping_method' => 'pickup',
            ]);
            $this->fail('Expected Stripe-disabled cash checkout to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(__('api.marketplace_stripe_disabled'), $exception->getMessage());
        }

        $this->assertDatabaseMissing('marketplace_orders', [
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyer->id,
        ]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'inventory_count' => 1,
        ]);
    }

    public function test_createFromOffer_releases_reservation_for_unlimited_inventory_listing(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => 'acct_order_service_ready',
            'stripe_onboarding_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Unlimited offer listing',
            'description' => 'Accepted offers must not sell out NULL inventory.',
            'price' => 8.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'inventory_count' => null,
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'reserved',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $offer = new MarketplaceOffer();
        $offer->tenant_id = $this->testTenantId;
        $offer->marketplace_listing_id = $listingId;
        $offer->buyer_id = $buyer->id;
        $offer->seller_id = $seller->id;
        $offer->amount = 8.00;
        $offer->currency = 'EUR';
        $offer->status = 'accepted';
        $offer->accepted_at = now();
        $offer->expires_at = now()->addHour();
        $offer->save();

        $order = MarketplaceOrderService::createFromOffer($offer, [
            'listing_id' => $listingId,
            'idempotency_key' => 'offer-unlimited-inventory-' . $offer->id,
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
        ]);

        $this->assertSame($listingId, (int) $order->marketplace_listing_id);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $listingId,
            'status' => 'active',
            'inventory_count' => null,
        ]);
    }

    public function test_createFromOffer_can_retry_after_cancelled_unpaid_attempt(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $buyer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'stripe_account_id' => 'acct_order_retry_ready',
            'stripe_onboarding_complete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Retryable accepted offer',
            'description' => 'A cancelled checkout must not consume the accepted offer forever.',
            'price' => 12.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'inventory_count' => null,
            'quantity' => 1,
            'local_pickup' => true,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'reserved',
            'moderation_status' => 'approved',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $offer = MarketplaceOffer::create([
            'tenant_id' => $this->testTenantId,
            'marketplace_listing_id' => $listingId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 12.00,
            'currency' => 'EUR',
            'status' => 'accepted',
            'accepted_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        $data = [
            'listing_id' => $listingId,
            'idempotency_key' => 'accepted-offer-retry-' . $offer->id,
            'payment_method' => 'cash',
            'shipping_method' => 'pickup',
        ];

        $first = MarketplaceOrderService::createFromOffer($offer, $data);
        MarketplaceOrderService::cancel($first, 'payment_expired');
        $second = MarketplaceOrderService::createFromOffer($offer->fresh(), $data);

        $this->assertNotSame((int) $first->id, (int) $second->id);
        $this->assertSame('pending_payment', (string) $second->status);
        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $first->id,
            'status' => 'cancelled',
            'marketplace_offer_id' => null,
            'checkout_key' => null,
        ]);
        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $second->id,
            'marketplace_offer_id' => $offer->id,
        ]);
    }

    // -----------------------------------------------------------------
    //  markShipped
    // -----------------------------------------------------------------

    public function test_markShipped_throwsWhenOrderNotPaid(): void
    {
        $order = $this->makePersistedOrder('pending_payment');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be paid before marking as shipped');

        MarketplaceOrderService::markShipped($order, []);
    }

    public function test_markShipped_sets_tracking_without_overwriting_checkout_shipping_method(): void
    {
        $order = $this->makePersistedOrder('paid', ['shipping_method' => 'pickup']);

        $result = MarketplaceOrderService::markShipped($order, [
            'shipping_method' => 'express',
            'tracking_number' => 'TRACK123',
            'tracking_url' => 'https://track.example.com/TRACK123',
        ]);

        $this->assertEquals('shipped', $result->status);
        $this->assertEquals('TRACK123', $result->tracking_number);
        $this->assertEquals('https://track.example.com/TRACK123', $result->tracking_url);
        $this->assertEquals('pickup', $result->shipping_method);
        $this->assertNotNull($result->seller_confirmed_at);
    }

    public function test_markShipped_keepsExistingShippingMethodWhenNotProvided(): void
    {
        $order = $this->makePersistedOrder('paid', ['shipping_method' => 'standard']);

        $result = MarketplaceOrderService::markShipped($order, [
            'tracking_number' => 'TR456',
        ]);

        $this->assertEquals('shipped', $result->status);
        $this->assertEquals('standard', $result->shipping_method);
        $this->assertEquals('TR456', $result->tracking_number);
    }

    public function test_markShipped_cannot_overwrite_a_concurrent_refund(): void
    {
        $staleOrder = $this->makePersistedOrder('paid');
        DB::table('marketplace_orders')->where('id', $staleOrder->id)->update([
            'status' => 'refunded',
            'updated_at' => now(),
        ]);

        try {
            MarketplaceOrderService::markShipped($staleOrder, []);
            $this->fail('Expected the locked status re-check to reject the transition.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                __('api.marketplace_order_paid_before_shipping'),
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $staleOrder->id,
            'status' => 'refunded',
        ]);
    }

    // -----------------------------------------------------------------
    //  confirmDelivery
    // -----------------------------------------------------------------

    public function test_confirmDelivery_throwsWhenOrderInPendingPayment(): void
    {
        $order = $this->makePersistedOrder('pending_payment');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be shipped or paid');

        MarketplaceOrderService::confirmDelivery($order);
    }

    public function test_confirmDelivery_setsDeliveredStatusWithAutoComplete(): void
    {
        $order = $this->makePersistedOrder('shipped');

        $result = MarketplaceOrderService::confirmDelivery($order);

        $this->assertEquals('delivered', $result->status);
        $this->assertNotNull($result->buyer_confirmed_at);
        $this->assertNotNull($result->auto_complete_at);
        $this->assertTrue($result->auto_complete_at->isFuture());
    }

    public function test_confirmDelivery_worksForPaidStatusToo(): void
    {
        $order = $this->makePersistedOrder('paid');

        $result = MarketplaceOrderService::confirmDelivery($order);

        $this->assertEquals('delivered', $result->status);
    }

    public function test_confirmDelivery_cannot_overwrite_a_concurrent_dispute(): void
    {
        $staleOrder = $this->makePersistedOrder('shipped');
        DB::table('marketplace_orders')->where('id', $staleOrder->id)->update([
            'status' => 'disputed',
            'updated_at' => now(),
        ]);

        try {
            MarketplaceOrderService::confirmDelivery($staleOrder);
            $this->fail('Expected the locked status re-check to reject the transition.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                __('api.marketplace_order_delivery_state_invalid'),
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('marketplace_orders', [
            'id' => $staleOrder->id,
            'status' => 'disputed',
        ]);
    }

    // -----------------------------------------------------------------
    //  cancel
    // -----------------------------------------------------------------

    public function test_cancel_throwsWhenOrderAlreadyShipped(): void
    {
        $order = $this->makePersistedOrder('shipped');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only an unpaid order can be cancelled');

        MarketplaceOrderService::cancel($order, 'changed mind');
    }

    public function test_cancel_throwsWhenOrderIsCompleted(): void
    {
        $order = $this->makePersistedOrder('completed');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only an unpaid order can be cancelled');

        MarketplaceOrderService::cancel($order, 'too late');
    }

    public function test_cancel_throwsWhenOrderIsRefunded(): void
    {
        $order = $this->makePersistedOrder('refunded');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only an unpaid order can be cancelled');

        MarketplaceOrderService::cancel($order, 'too late');
    }

    public function test_cancel_throwsWhenOrderIsPaid(): void
    {
        // A paid order must be refunded, not cancelled — cancel() moves no money,
        // so voiding it would leave the buyer charged with no goods and no refund.
        $order = $this->makePersistedOrder('paid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only an unpaid order can be cancelled');

        MarketplaceOrderService::cancel($order, 'changed mind');
    }

    // -----------------------------------------------------------------
    //  complete
    // -----------------------------------------------------------------

    public function test_complete_preventsDoubleCompletion(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'completed';

        $result = MarketplaceOrderService::complete($order);

        $this->assertEquals('completed', $result->status);
    }

    public function test_complete_throwsWhenOrderNotInCompletableState(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'pending_payment';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be delivered before it can be completed');

        MarketplaceOrderService::complete($order);
    }

    public function test_complete_throwsWhenOrderIsCancelled(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'cancelled';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be delivered');

        MarketplaceOrderService::complete($order);
    }

    public function test_formatOrder_includesListingDeliveryMethod(): void
    {
        $order = new MarketplaceOrder([
            'order_number' => 'MKT-000123',
            'status' => 'paid',
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
        ]);
        $order->id = 123;

        $listing = new MarketplaceListing([
            'title' => 'Community vase',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'status' => 'sold',
            'delivery_method' => 'community_delivery',
        ]);
        $listing->id = 456;

        $order->setRelation('listing', $listing);

        $formatted = MarketplaceOrderService::formatOrder($order);

        $this->assertSame('community_delivery', $formatted['listing']['delivery_method']);
    }

    // -----------------------------------------------------------------
    //  generateOrderNumber
    // -----------------------------------------------------------------

    public function test_generateOrderNumber_format(): void
    {
        $orderNumber = MarketplaceOrderService::generateOrderNumber($this->testTenantId);

        $this->assertMatchesRegularExpression('/^MKT-[0-9A-HJKMNP-TV-Z]{26}$/', $orderNumber);
    }

    public function test_generateOrderNumber_isUniqueWithoutReadingPreviousOrders(): void
    {
        $numbers = [];
        for ($i = 0; $i < 100; $i++) {
            $numbers[] = MarketplaceOrderService::generateOrderNumber($this->testTenantId);
        }

        $this->assertCount(100, array_unique($numbers));
    }
}
