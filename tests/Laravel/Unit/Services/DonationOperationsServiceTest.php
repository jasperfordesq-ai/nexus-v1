<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\DonationOperationsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class DonationOperationsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $operationsTenantId = 987654;

    public function test_overview_separates_platform_fallback_tenant_connect_gift_aid_and_disputes(): void
    {
        $tenantId = $this->operationsTenantId;
        $platformPi = 'pi_platform_ready_' . uniqid();
        $connectPi = 'pi_connect_ready_' . uniqid();
        $disputeId = 'dp_open_' . uniqid();

        DB::table('vol_donations')->insert([
            [
                'tenant_id' => $tenantId,
                'user_id' => 101,
                'amount' => 10.00,
                'currency' => 'GBP',
                'payment_method' => 'stripe',
                'payment_reference' => '',
                'payment_route' => 'platform_default',
                'stripe_payment_intent_id' => $platformPi,
                'status' => 'completed',
                'gift_aid_claim_status' => 'ready',
                'gift_aid_declaration_name' => 'A Donor',
                'gift_aid_postcode' => 'SW1A 1AA',
                'stripe_account_id' => null,
                'created_at' => '2026-04-05 12:00:00',
            ],
            [
                'tenant_id' => $tenantId,
                'user_id' => 102,
                'amount' => 25.00,
                'currency' => 'GBP',
                'payment_method' => 'stripe',
                'payment_reference' => '',
                'payment_route' => 'tenant_connect',
                'stripe_account_id' => 'acct_ready123',
                'stripe_payment_intent_id' => $connectPi,
                'status' => 'completed',
                'gift_aid_claim_status' => 'not_eligible',
                'gift_aid_declaration_name' => null,
                'gift_aid_postcode' => null,
                'created_at' => '2026-04-06 12:00:00',
            ],
        ]);

        DB::table('donation_disputes')->insert([
            'tenant_id' => $tenantId,
            'stripe_dispute_id' => $disputeId,
            'payment_intent_id' => $platformPi,
            'amount' => 1000,
            'currency' => 'gbp',
            'status' => 'needs_response',
            'reason' => 'fraudulent',
            'payment_route' => 'platform_default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $overview = DonationOperationsService::overview($tenantId);

        $this->assertSame(3500, $overview['totals']['completed_cents']);
        $this->assertSame(1000, $overview['routing']['platform_fallback_cents']);
        $this->assertSame(2500, $overview['routing']['tenant_connect_cents']);
        $this->assertSame(1000, $overview['gift_aid']['ready_cents']);
        $this->assertSame(1, $overview['disputes']['open_count']);
    }

    public function test_exports_include_gift_aid_and_annual_donor_receipt_rows(): void
    {
        $tenantId = $this->operationsTenantId;
        $paymentIntentId = 'pi_giftaid_' . uniqid();

        DB::table('vol_donations')->insert([
            'tenant_id' => $tenantId,
            'user_id' => 201,
            'amount' => 12.34,
            'currency' => 'GBP',
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'payment_route' => 'platform_default',
            'stripe_payment_intent_id' => $paymentIntentId,
            'status' => 'completed',
            'donor_name' => 'Gift Aid Donor',
            'donor_email' => 'gift-aid@example.test',
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Gift Aid Donor',
            'gift_aid_address_line1' => '1 Test Street',
            'gift_aid_postcode' => 'SW1A 1AA',
            'gift_aid_country' => 'GB',
            'gift_aid_consented_at' => '2026-04-01 10:00:00',
            'fund_code' => 'general',
            'created_at' => '2026-04-05 12:00:00',
        ]);

        $giftAidRows = DonationOperationsService::giftAidExportRows($tenantId);
        $annualRows = DonationOperationsService::annualReceiptRows($tenantId, 2026);

        $this->assertSame('Gift Aid Donor', $giftAidRows[0]['declaration_name']);
        $this->assertSame('SW1A 1AA', $giftAidRows[0]['postcode']);
        $this->assertSame('general', $annualRows[0]['fund_code']);
        $this->assertSame('12.34', $annualRows[0]['amount']);
    }

    public function test_gift_aid_export_claim_and_overview_exclude_non_gbp_donations(): void
    {
        $tenantId = $this->operationsTenantId;

        $giftAidRow = static fn (string $currency, string $pi): array => [
            'tenant_id' => $tenantId,
            'user_id' => 401,
            'amount' => 20.00,
            'currency' => $currency,
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'payment_route' => 'platform_default',
            'stripe_payment_intent_id' => $pi,
            'status' => 'completed',
            'donor_name' => 'Currency Guard Donor',
            'donor_email' => 'currency-guard@example.test',
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Currency Guard Donor',
            'gift_aid_address_line1' => '1 Test Street',
            'gift_aid_postcode' => 'SW1A 1AA',
            'gift_aid_country' => 'GB',
            'gift_aid_consented_at' => '2026-04-01 10:00:00',
            'created_at' => '2026-04-05 12:00:00',
        ];

        $gbpId = (int) DB::table('vol_donations')->insertGetId($giftAidRow('GBP', 'pi_ga_gbp_' . uniqid()));
        // Legacy row stamped 'ready' before the GBP guard existed — must be
        // excluded from every HMRC-facing layer.
        $eurId = (int) DB::table('vol_donations')->insertGetId($giftAidRow('EUR', 'pi_ga_eur_' . uniqid()));

        $overview = DonationOperationsService::overview($tenantId);
        $this->assertSame(2000, $overview['gift_aid']['ready_cents']);
        $this->assertSame(1, $overview['gift_aid']['ready_count']);

        $rows = DonationOperationsService::giftAidExportRows($tenantId);
        $exportedIds = array_column($rows, 'donation_id');
        $this->assertContains($gbpId, $exportedIds);
        $this->assertNotContains($eurId, $exportedIds);

        $claimed = DonationOperationsService::markGiftAidRowsClaimed($tenantId, [$gbpId, $eurId]);

        $this->assertSame(1, $claimed);
        $this->assertSame('claimed', DB::table('vol_donations')->where('id', $gbpId)->value('gift_aid_claim_status'));
        // The EUR row must never be stamped as claimed with HMRC, even when
        // its ID is passed explicitly.
        $this->assertSame('ready', DB::table('vol_donations')->where('id', $eurId)->value('gift_aid_claim_status'));
        $this->assertNull(DB::table('vol_donations')->where('id', $eurId)->value('gift_aid_claimed_at'));
    }

    public function test_gift_aid_ready_totals_and_export_net_partial_refunds(): void
    {
        $tenantId = $this->operationsTenantId;

        DB::table('vol_donations')->insert([
            'tenant_id' => $tenantId,
            'user_id' => 501,
            'amount' => 20.00,
            'amount_refunded' => 5.00,
            'currency' => 'GBP',
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'payment_route' => 'platform_default',
            'stripe_payment_intent_id' => 'pi_ga_partial_' . uniqid(),
            'status' => 'completed',
            'donor_name' => 'Partial Refund Donor',
            'donor_email' => 'partial-refund@example.test',
            'gift_aid_claim_status' => 'ready',
            'gift_aid_declaration_name' => 'Partial Refund Donor',
            'gift_aid_address_line1' => '1 Test Street',
            'gift_aid_postcode' => 'SW1A 1AA',
            'gift_aid_country' => 'GB',
            'gift_aid_consented_at' => '2026-04-01 10:00:00',
            'created_at' => '2026-04-05 12:00:00',
        ]);

        // Only the retained 15.00 may be claimed with HMRC (2026-07-10 audit M1);
        // the overview must mirror what the export will emit.
        $overview = DonationOperationsService::overview($tenantId);
        $this->assertSame(1500, $overview['gift_aid']['ready_cents']);
        $this->assertSame(1, $overview['gift_aid']['ready_count']);

        $rows = DonationOperationsService::giftAidExportRows($tenantId);
        $this->assertCount(1, $rows);
        $this->assertSame('15.00', $rows[0]['amount']);
    }

    public function test_record_stripe_dispute_is_tenant_scoped_and_idempotent(): void
    {
        $tenantId = $this->operationsTenantId;
        $paymentIntentId = 'pi_disputed_' . uniqid();
        DB::table('vol_donations')->insert([
            'tenant_id' => $tenantId,
            'user_id' => 301,
            'amount' => 10.00,
            'currency' => 'GBP',
            'payment_method' => 'stripe',
            'payment_reference' => '',
            'payment_route' => 'tenant_connect',
            'stripe_account_id' => 'acct_dispute',
            'stripe_payment_intent_id' => $paymentIntentId,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        $event = (object) [
            'id' => 'dp_123',
            'payment_intent' => $paymentIntentId,
            'charge' => 'ch_123',
            'amount' => 1000,
            'currency' => 'gbp',
            'status' => 'needs_response',
            'reason' => 'fraudulent',
            'evidence_details' => (object) ['due_by' => 1780000000],
            'metadata' => (object) ['nexus_tenant_id' => (string) $tenantId],
        ];

        DonationOperationsService::recordStripeDispute($event);
        DonationOperationsService::recordStripeDispute($event);

        $this->assertSame(1, DB::table('donation_disputes')->where('stripe_dispute_id', 'dp_123')->count());
        $this->assertDatabaseHas('donation_disputes', [
            'tenant_id' => $tenantId,
            'payment_intent_id' => $paymentIntentId,
            'payment_route' => 'tenant_connect',
            'stripe_account_id' => 'acct_dispute',
        ]);
    }
}
