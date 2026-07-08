<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Webhooks;

use App\Core\TenantContext;
use App\Services\StripeDonationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the Stripe donation state machine (module audit,
 * round 2): out-of-order / replayed webhook events must not resurrect refunded
 * donations, double-count giving-day totals, drive them negative, or treat a
 * partial refund as a full refund. Also the tenant-currency restriction.
 */
class StripeDonationStateMachineTest extends TestCase
{
    use DatabaseTransactions;

    private function seedGivingDay(float $raised): int
    {
        return (int) DB::table('vol_giving_days')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'title' => 'Test Giving Day',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'created_by' => 1,
            'raised_amount' => $raised,
            'created_at' => now(),
        ]);
    }

    private function seedDonation(string $status, int $givingDayId, string $pi, float $amount = 10.0): int
    {
        return (int) DB::table('vol_donations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => 1,
            'giving_day_id' => $givingDayId,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => $status,
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'stripe_payment_intent_id' => $pi,
            'created_at' => now(),
        ]);
    }

    private function pi(string $id): object
    {
        return (object) ['id' => $id, 'metadata' => (object) ['nexus_tenant_id' => (string) $this->testTenantId]];
    }

    public function test_succeeded_event_does_not_resurrect_refunded_donation(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(0.0);
        $donationId = $this->seedDonation('refunded', $gd, 'pi_resurrect_1');

        StripeDonationService::handlePaymentSucceeded($this->pi('pi_resurrect_1'));

        // Still refunded, giving day NOT incremented.
        $this->assertSame('refunded', DB::table('vol_donations')->where('id', $donationId)->value('status'));
        $this->assertEquals(0.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'));
    }

    public function test_succeeded_event_completes_pending_and_increments_once(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(0.0);
        $donationId = $this->seedDonation('pending', $gd, 'pi_once_1');

        StripeDonationService::handlePaymentSucceeded($this->pi('pi_once_1'));
        // A replayed delivery of the same event must not double-count.
        StripeDonationService::handlePaymentSucceeded($this->pi('pi_once_1'));

        $this->assertSame('completed', DB::table('vol_donations')->where('id', $donationId)->value('status'));
        $this->assertEquals(10.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'));
    }

    public function test_late_failed_event_does_not_clobber_completed_donation(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_latefail_1');

        StripeDonationService::handlePaymentFailed($this->pi('pi_latefail_1'));

        $this->assertSame('completed', DB::table('vol_donations')->where('id', $donationId)->value('status'));
    }

    public function test_partial_refund_is_ignored(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_partial_1', 10.0);

        // Charge of 1000 minor units, only 400 refunded.
        $charge = (object) [
            'payment_intent' => 'pi_partial_1',
            'metadata' => (object) ['nexus_tenant_id' => (string) $this->testTenantId],
            'amount' => 1000,
            'amount_refunded' => 400,
        ];
        StripeDonationService::handleChargeRefunded($charge);

        // Not refunded; giving day total untouched.
        $this->assertSame('completed', DB::table('vol_donations')->where('id', $donationId)->value('status'));
        $this->assertEquals(10.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'));
    }

    public function test_full_refund_of_pending_donation_does_not_go_negative(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(0.0);
        $donationId = $this->seedDonation('pending', $gd, 'pi_negguard_1', 10.0);

        $charge = (object) [
            'payment_intent' => 'pi_negguard_1',
            'metadata' => (object) ['nexus_tenant_id' => (string) $this->testTenantId],
            'amount' => 1000,
            'amount_refunded' => 1000,
        ];
        StripeDonationService::handleChargeRefunded($charge);

        // Refunded, but the giving day was never incremented so it must not go negative.
        $this->assertSame('refunded', DB::table('vol_donations')->where('id', $donationId)->value('status'));
        $this->assertEquals(0.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'));
    }

    public function test_createPaymentIntent_rejects_currency_mismatch(): void
    {
        // Tenant 2 is configured for EUR; a USD donation must be rejected before
        // any Stripe call.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Donation currency must match the community currency');

        StripeDonationService::createPaymentIntent(1, $this->testTenantId, [
            'amount' => 10.00,
            'currency' => 'usd',
        ]);
    }
}
