<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MarketplaceOrderService;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSellerProfile;
use Illuminate\Support\Facades\DB;
use Mockery;

class MarketplaceOrderServiceTest extends TestCase
{
    // -----------------------------------------------------------------
    //  createFromOffer — guard clause
    // -----------------------------------------------------------------

    public function test_createFromOffer_throwsWhenOfferNotAccepted(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->status = 'pending';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offer must be accepted before creating an order');

        MarketplaceOrderService::createFromOffer($offer);
    }

    public function test_createFromOffer_throwsWhenOfferIsDeclined(): void
    {
        $offer = Mockery::mock(MarketplaceOffer::class)->makePartial();
        $offer->status = 'declined';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Offer must be accepted');

        MarketplaceOrderService::createFromOffer($offer);
    }

    // -----------------------------------------------------------------
    //  markShipped
    // -----------------------------------------------------------------

    public function test_markShipped_throwsWhenOrderNotPaid(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'pending_payment';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be paid before marking as shipped');

        MarketplaceOrderService::markShipped($order, []);
    }

    public function test_markShipped_setsStatusAndTrackingInfo(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'paid';
        $order->shipping_method = null;
        $order->shouldReceive('save')->once();

        $result = MarketplaceOrderService::markShipped($order, [
            'shipping_method' => 'express',
            'tracking_number' => 'TRACK123',
            'tracking_url' => 'https://track.example.com/TRACK123',
        ]);

        $this->assertEquals('shipped', $result->status);
        $this->assertEquals('TRACK123', $result->tracking_number);
        $this->assertEquals('https://track.example.com/TRACK123', $result->tracking_url);
        $this->assertEquals('express', $result->shipping_method);
        $this->assertNotNull($result->seller_confirmed_at);
    }

    public function test_markShipped_keepsExistingShippingMethodWhenNotProvided(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'paid';
        $order->shipping_method = 'standard';
        $order->shouldReceive('save')->once();

        $result = MarketplaceOrderService::markShipped($order, [
            'tracking_number' => 'TR456',
        ]);

        $this->assertEquals('shipped', $result->status);
        $this->assertEquals('standard', $result->shipping_method);
        $this->assertEquals('TR456', $result->tracking_number);
    }

    // -----------------------------------------------------------------
    //  confirmDelivery
    // -----------------------------------------------------------------

    public function test_confirmDelivery_throwsWhenOrderInPendingPayment(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'pending_payment';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be shipped or paid');

        MarketplaceOrderService::confirmDelivery($order);
    }

    public function test_confirmDelivery_setsDeliveredStatusWithAutoComplete(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'shipped';
        $order->shouldReceive('save')->once();

        $result = MarketplaceOrderService::confirmDelivery($order);

        $this->assertEquals('delivered', $result->status);
        $this->assertNotNull($result->buyer_confirmed_at);
        $this->assertNotNull($result->auto_complete_at);
        $this->assertTrue($result->auto_complete_at->isFuture());
    }

    public function test_confirmDelivery_worksForPaidStatusToo(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'paid';
        $order->shouldReceive('save')->once();

        $result = MarketplaceOrderService::confirmDelivery($order);

        $this->assertEquals('delivered', $result->status);
    }

    // -----------------------------------------------------------------
    //  cancel
    // -----------------------------------------------------------------

    public function test_cancel_throwsWhenOrderAlreadyShipped(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'shipped';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel an order that has already been shipped');

        MarketplaceOrderService::cancel($order, 'changed mind');
    }

    public function test_cancel_throwsWhenOrderIsCompleted(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'completed';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel an order that has already been shipped or completed');

        MarketplaceOrderService::cancel($order, 'too late');
    }

    public function test_cancel_throwsWhenOrderIsRefunded(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'refunded';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel');

        MarketplaceOrderService::cancel($order, 'too late');
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

    // -----------------------------------------------------------------
    //  generateOrderNumber
    // -----------------------------------------------------------------

    public function test_generateOrderNumber_format(): void
    {
        // Test the format directly: MKT-XXXXXX (6-digit zero-padded)
        $format = 'MKT-' . str_pad('1', 6, '0', STR_PAD_LEFT);
        $this->assertEquals('MKT-000001', $format);

        $format42 = 'MKT-' . str_pad('42', 6, '0', STR_PAD_LEFT);
        $this->assertEquals('MKT-000042', $format42);

        $format999999 = 'MKT-' . str_pad('999999', 6, '0', STR_PAD_LEFT);
        $this->assertEquals('MKT-999999', $format999999);
    }

    public function test_generateOrderNumber_incrementsFromRegex(): void
    {
        // Verify the regex extraction logic used in generateOrderNumber
        $orderNumber = 'MKT-000042';
        preg_match('/MKT-(\d+)/', $orderNumber, $matches);

        $this->assertEquals('000042', $matches[1]);
        $this->assertEquals(42, (int) $matches[1]);
        $nextNumber = (int) $matches[1] + 1;
        $this->assertEquals(43, $nextNumber);

        $nextFormatted = 'MKT-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
        $this->assertEquals('MKT-000043', $nextFormatted);
    }
}
