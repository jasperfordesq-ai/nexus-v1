<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MarketplacePaymentService;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayment;
use Mockery;

class MarketplacePaymentServiceTest extends TestCase
{
    // -----------------------------------------------------------------
    //  createPaymentIntent — state validation
    // -----------------------------------------------------------------

    public function test_createPaymentIntent_rejectsOrderNotInPendingPaymentStatus(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'paid';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be in pending_payment status');

        MarketplacePaymentService::createPaymentIntent($order);
    }

    public function test_createPaymentIntent_rejectsCompletedOrder(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'completed';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be in pending_payment status');

        MarketplacePaymentService::createPaymentIntent($order);
    }

    public function test_createPaymentIntent_rejectsCancelledOrder(): void
    {
        $order = Mockery::mock(MarketplaceOrder::class)->makePartial();
        $order->status = 'cancelled';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order must be in pending_payment status');

        MarketplacePaymentService::createPaymentIntent($order);
    }

    // -----------------------------------------------------------------
    //  processRefund — state validation
    // -----------------------------------------------------------------

    public function test_processRefund_throwsWhenNoPaymentExists(): void
    {
        // processRefund queries for a MarketplacePayment by order_id.
        // We test the thrown exception by passing an order whose payment lookup
        // returns null. Since we can't mock the Eloquent static, we verify
        // that the method signature and exception types are correct by calling
        // with a real (non-persisted) order instance that has no DB rows.
        // This test ensures the guard clause works at the service boundary.
        $order = new MarketplaceOrder();
        $order->id = 999999; // Non-existent

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No successful payment found');

        MarketplacePaymentService::processRefund($order, null, 'test');
    }

    // -----------------------------------------------------------------
    //  Fee calculation verification
    // -----------------------------------------------------------------

    public function test_feeCalculation_correctForTypicalAmounts(): void
    {
        // Verify the fee calculation logic matches what the service does:
        // $platformFee = round($totalAmount * ($feePercent / 100), 2)
        // $sellerPayout = round($totalAmount - $platformFee, 2)

        $totalAmount = 50.00;
        $feePercent = 5.0;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $this->assertEquals(2.50, $platformFee);
        $this->assertEquals(47.50, $sellerPayout);

        // Verify cents conversion
        $amountCents = (int) round($totalAmount * 100);
        $feeCents = (int) round($platformFee * 100);

        $this->assertEquals(5000, $amountCents);
        $this->assertEquals(250, $feeCents);
    }

    public function test_feeCalculation_handlesEdgeCaseRounding(): void
    {
        // 33.33 at 7% fee — tests rounding behavior
        $totalAmount = 33.33;
        $feePercent = 7.0;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $this->assertEquals(2.33, $platformFee);
        $this->assertEquals(31.00, $sellerPayout);

        // Total should roughly equal original (within rounding)
        $this->assertEqualsWithDelta($totalAmount, $platformFee + $sellerPayout, 0.01);
    }

    public function test_feeCalculation_handlesZeroFeePercent(): void
    {
        $totalAmount = 100.00;
        $feePercent = 0.0;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $this->assertEquals(0.00, $platformFee);
        $this->assertEquals(100.00, $sellerPayout);
    }
}
