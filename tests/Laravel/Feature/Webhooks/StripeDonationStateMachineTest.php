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

    private function partialRefundCharge(string $pi, int $amount, int $amountRefunded): object
    {
        return (object) [
            'payment_intent' => $pi,
            'metadata' => (object) ['nexus_tenant_id' => (string) $this->testTenantId],
            'amount' => $amount,
            'amount_refunded' => $amountRefunded,
            'currency' => 'eur',
        ];
    }

    /**
     * 2026-07-10 audit M1: partial refunds used to be ignored entirely — the
     * donation stayed completed at its full amount and the giving day stayed
     * overstated. The refunded slice must now be delta-applied, idempotently
     * (Stripe reports amount_refunded cumulatively and redelivers events).
     */
    public function test_partial_refund_keeps_donation_completed_but_accounts_the_refunded_slice(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_partial_1', 10.0);

        // First partial refund: 400 of 1000 minor units.
        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_1', 1000, 400));

        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('completed', $row->status, 'partial refund must not flip status');
        $this->assertEqualsWithDelta(4.0, (float) $row->amount_refunded, 0.001);
        $this->assertEqualsWithDelta(6.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'), 0.001);

        // Replayed delivery of the same cumulative total: no double decrement.
        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_1', 1000, 400));
        $this->assertEqualsWithDelta(6.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'), 0.001);

        // Second partial refund: cumulative 700 → only the 300 delta comes off.
        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_1', 1000, 700));
        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('completed', $row->status);
        $this->assertEqualsWithDelta(7.0, (float) $row->amount_refunded, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'), 0.001);

        // Final full refund: only the unaccounted remainder (3.00) comes off.
        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_1', 1000, 1000));
        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('refunded', $row->status);
        $this->assertEqualsWithDelta(10.0, (float) $row->amount_refunded, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'), 0.001);
    }

    public function test_partial_refund_nets_the_gift_aid_export_and_overview(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_partial_ga_1', 10.0);
        DB::table('vol_donations')->where('id', $donationId)->update([
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Partial Refund Donor',
            'currency' => 'GBP',
        ]);

        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_ga_1', 1000, 400));

        // Still claimable, but only for the retained 6.00 — never the full 10.00.
        $rows = \App\Services\DonationOperationsService::giftAidExportRows($this->testTenantId);
        $exported = collect($rows)->firstWhere('donation_id', $donationId);
        $this->assertNotNull($exported, 'partially refunded ready row must still export its retained slice');
        $this->assertSame('6.00', $exported['amount']);

        // A fully refunded donation leaves the export via its status.
        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_ga_1', 1000, 1000));
        $secondIds = array_column(\App\Services\DonationOperationsService::giftAidExportRows($this->testTenantId), 'donation_id');
        $this->assertNotContains($donationId, $secondIds);
    }

    public function test_partial_refund_of_claimed_donation_is_flagged_for_hmrc_adjustment(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_partial_claimed_1', 10.0);
        DB::table('vol_donations')->where('id', $donationId)->update([
            'gift_aid_claim_status' => 'claimed',
            'gift_aid_claimed_at' => now(),
            'currency' => 'GBP',
        ]);

        StripeDonationService::handleChargeRefunded($this->partialRefundCharge('pi_partial_claimed_1', 1000, 400));

        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('completed', $row->status, 'partially refunded donation stays completed');
        $this->assertSame('refund_after_claim', $row->gift_aid_claim_status);
        $this->assertNotNull($row->gift_aid_claimed_at, 'claimed_at kept as evidence');
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

    public function test_gift_aid_export_stamps_rows_claimed_and_excludes_them_next_time(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(0.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_giftaid_1');
        DB::table('vol_donations')->where('id', $donationId)->update([
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Test Donor',
            // Gift Aid is a UK/HMRC scheme — only GBP donations qualify for export
            // (giftAidExportRows() now enforces this), so a claimable row must be GBP.
            'currency' => 'GBP',
        ]);

        $rows = \App\Services\DonationOperationsService::giftAidExportRows($this->testTenantId);
        $exportedIds = array_map(static fn (array $r): int => $r['donation_id'], $rows);
        $this->assertContains($donationId, $exportedIds);

        $stamped = \App\Services\DonationOperationsService::markGiftAidRowsClaimed($this->testTenantId, $exportedIds);
        $this->assertGreaterThanOrEqual(1, $stamped);

        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('claimed', $row->gift_aid_claim_status);
        $this->assertNotNull($row->gift_aid_claimed_at);

        // A second export must NOT include the claimed row (no HMRC double-claim).
        $secondIds = array_map(
            static fn (array $r): int => $r['donation_id'],
            \App\Services\DonationOperationsService::giftAidExportRows($this->testTenantId)
        );
        $this->assertNotContains($donationId, $secondIds);

        // Stamping again is a no-op (already claimed).
        $this->assertSame(0, \App\Services\DonationOperationsService::markGiftAidRowsClaimed($this->testTenantId, [$donationId]));
    }

    public function test_refund_of_claimed_donation_is_flagged_for_hmrc_adjustment(): void
    {
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_giftaid_refund_1');
        DB::table('vol_donations')->where('id', $donationId)->update([
            'gift_aid_claim_status' => 'claimed',
            'gift_aid_claimed_at' => now(),
        ]);

        $charge = (object) [
            'payment_intent' => 'pi_giftaid_refund_1',
            'metadata' => (object) ['nexus_tenant_id' => (string) $this->testTenantId],
            'amount' => 1000,
            'amount_refunded' => 1000,
        ];
        StripeDonationService::handleChargeRefunded($charge);

        $row = DB::table('vol_donations')->where('id', $donationId)->first();
        $this->assertSame('refunded', $row->status);
        $this->assertSame('refund_after_claim', $row->gift_aid_claim_status);
        $this->assertNotNull($row->gift_aid_claimed_at, 'claimed_at kept as evidence');
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

    public function test_admin_refund_and_webhook_do_not_double_decrement_giving_day(): void
    {
        // VOL-BE-003: createRefund() fires a charge.refunded webhook, so the admin
        // transaction and the webhook can both decrement the same donation's giving
        // day. The shared, locked refund-ledger step must decrement exactly once.
        TenantContext::setById($this->testTenantId);
        $gd = $this->seedGivingDay(10.0);
        $donationId = $this->seedDonation('completed', $gd, 'pi_refund_race_1', 10.0);

        // Both paths read the row as 'completed' before either commits (the race
        // window). Drive the shared ledger step twice with that pre-read row.
        $donation = DB::table('vol_donations')->where('id', $donationId)->first();

        $apply = new \ReflectionMethod(StripeDonationService::class, 'applyDonationRefund');
        $apply->setAccessible(true);
        $apply->invoke(null, $donation); // e.g. admin createRefund() transaction
        $apply->invoke(null, $donation); // e.g. racing charge.refunded webhook

        $this->assertSame('refunded', DB::table('vol_donations')->where('id', $donationId)->value('status'));
        $this->assertEqualsWithDelta(
            0.0,
            (float) DB::table('vol_giving_days')->where('id', $gd)->value('raised_amount'),
            0.001,
            'giving day decremented exactly once, not twice'
        );
    }
}
