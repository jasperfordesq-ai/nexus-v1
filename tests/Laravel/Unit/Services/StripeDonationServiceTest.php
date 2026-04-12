<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\StripeDonationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;
use Mockery;

/**
 * @covers \App\Services\StripeDonationService
 */
class StripeDonationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // =========================================================================
    // createPaymentIntent — validation
    // =========================================================================

    public function test_createPaymentIntent_throwsOnAmountBelowMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Donation amount must be at least 0.50');

        StripeDonationService::createPaymentIntent(1, $this->testTenantId, [
            'amount' => 0.10,
            'currency' => 'eur',
        ]);
    }

    public function test_createPaymentIntent_throwsOnInvalidCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO code');

        StripeDonationService::createPaymentIntent(1, $this->testTenantId, [
            'amount' => 10.00,
            'currency' => 'euro',
        ]);
    }

    public function test_createPaymentIntent_throwsWhenUserNotFound(): void
    {
        DB::shouldReceive('table->where->where->first')->once()->andReturnNull();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        StripeDonationService::createPaymentIntent(999, $this->testTenantId, [
            'amount' => 10.00,
            'currency' => 'eur',
        ]);
    }

    // =========================================================================
    // handlePaymentSucceeded
    // =========================================================================

    public function test_handlePaymentSucceeded_skipsWhenNoPaymentIntentId(): void
    {
        Log::shouldReceive('warning')->once()->with(
            'Stripe donation: payment_intent.succeeded with no ID'
        );

        $paymentIntent = (object) ['id' => null];
        StripeDonationService::handlePaymentSucceeded($paymentIntent);
    }

    public function test_handlePaymentSucceeded_skipsWhenDonationNotFound(): void
    {
        DB::shouldReceive('table->where->first')->once()->andReturnNull();

        Log::shouldReceive('info')->once();

        $paymentIntent = (object) ['id' => 'pi_test_123'];
        StripeDonationService::handlePaymentSucceeded($paymentIntent);
    }

    public function test_handlePaymentSucceeded_isIdempotentForCompletedDonation(): void
    {
        $donation = (object) [
            'id' => 1,
            'status' => 'completed',
            'stripe_payment_intent_id' => 'pi_test_123',
        ];

        DB::shouldReceive('table->where->first')->once()->andReturn($donation);

        Log::shouldReceive('info')->once();

        $paymentIntent = (object) ['id' => 'pi_test_123'];
        StripeDonationService::handlePaymentSucceeded($paymentIntent);
        // No DB update should be attempted — idempotent skip
    }

    // =========================================================================
    // handlePaymentFailed
    // =========================================================================

    public function test_handlePaymentFailed_skipsWhenNoPaymentIntentId(): void
    {
        Log::shouldReceive('warning')->once()->with(
            'Stripe donation: payment_intent.payment_failed with no ID'
        );

        $paymentIntent = (object) ['id' => null];
        StripeDonationService::handlePaymentFailed($paymentIntent);
    }

    public function test_handlePaymentFailed_skipsWhenDonationNotFound(): void
    {
        DB::shouldReceive('table->where->first')->once()->andReturnNull();

        Log::shouldReceive('info')->once();

        $paymentIntent = (object) ['id' => 'pi_fail_456'];
        StripeDonationService::handlePaymentFailed($paymentIntent);
    }

    // =========================================================================
    // handleChargeRefunded
    // =========================================================================

    public function test_handleChargeRefunded_skipsWhenNoPaymentIntentOnCharge(): void
    {
        Log::shouldReceive('warning')->once()->with(
            'Stripe donation: charge.refunded with no payment_intent'
        );

        $charge = (object) ['payment_intent' => null];
        StripeDonationService::handleChargeRefunded($charge);
    }

    public function test_handleChargeRefunded_isIdempotentForAlreadyRefunded(): void
    {
        $donation = (object) [
            'id' => 3,
            'status' => 'refunded',
            'stripe_payment_intent_id' => 'pi_ref_789',
        ];

        DB::shouldReceive('table->where->first')->once()->andReturn($donation);

        Log::shouldReceive('info')->once();

        $charge = (object) ['payment_intent' => 'pi_ref_789'];
        StripeDonationService::handleChargeRefunded($charge);
    }

    // =========================================================================
    // createRefund — validation
    // =========================================================================

    public function test_createRefund_throwsWhenDonationNotFound(): void
    {
        DB::shouldReceive('table->where->where->first')->once()->andReturnNull();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Donation not found');

        StripeDonationService::createRefund(999, $this->testTenantId);
    }

    public function test_createRefund_throwsWhenDonationNotCompleted(): void
    {
        $donation = (object) [
            'id' => 1,
            'status' => 'pending',
            'stripe_payment_intent_id' => 'pi_test_123',
            'tenant_id' => $this->testTenantId,
        ];

        DB::shouldReceive('table->where->where->first')->once()->andReturn($donation);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only completed donations can be refunded');

        StripeDonationService::createRefund(1, $this->testTenantId);
    }

    public function test_createRefund_throwsWhenNoStripePaymentId(): void
    {
        $donation = (object) [
            'id' => 1,
            'status' => 'completed',
            'stripe_payment_intent_id' => null,
            'tenant_id' => $this->testTenantId,
        ];

        DB::shouldReceive('table->where->where->first')->once()->andReturn($donation);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Donation has no associated Stripe payment');

        StripeDonationService::createRefund(1, $this->testTenantId);
    }

    // =========================================================================
    // getDonationReceipt
    // =========================================================================

    public function test_getDonationReceipt_returnsNullWhenDonationNotFound(): void
    {
        DB::shouldReceive('table->where->where->first')->once()->andReturnNull();

        $result = StripeDonationService::getDonationReceipt(999, 1, $this->testTenantId);

        $this->assertNull($result);
    }
}
