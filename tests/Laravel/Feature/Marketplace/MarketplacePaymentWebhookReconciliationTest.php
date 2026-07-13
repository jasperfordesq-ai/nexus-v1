<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Models\MarketplaceEscrow;
use App\Services\MarketplaceEscrowService;
use App\Services\MarketplacePaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplacePaymentWebhookReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private object $stripeHttp;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        config(['services.stripe.secret' => 'sk_test_marketplace_reconciliation']);
        $this->stripeHttp = new class implements \Stripe\HttpClient\ClientInterface {
            /** @var list<array{method:string,url:string,headers:array,params:array}> */
            public array $requests = [];

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1'): array
            {
                $this->requests[] = [
                    'method' => (string) $method,
                    'url' => (string) $absUrl,
                    'headers' => $headers,
                    'params' => $params,
                ];
                $id = str_contains((string) $absUrl, '/application_fees/')
                    ? 'fr_test_reconciliation'
                    : 'trr_test_reconciliation';

                return [json_encode([
                    'id' => $id,
                    'object' => str_starts_with($id, 'fr_') ? 'fee_refund' : 'transfer_reversal',
                    'amount' => (int) ($params['amount'] ?? 0),
                    'currency' => 'eur',
                ], JSON_THROW_ON_ERROR), 200, []];
            }
        };
        \Stripe\ApiRequestor::setHttpClient($this->stripeHttp);
        app()->instance(EmailDispatchService::class, new class extends EmailDispatchService {
            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                DB::table('email_log')->insert([
                    'tenant_id' => $options['tenant_id'] ?? null,
                    'recipient_email' => $to,
                    'category' => $options['category'] ?? null,
                    'subject' => $subject,
                    'provider' => 'smtp',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return true;
            }
        });
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /** @return array{buyer:User,seller:User,listing_id:int,order_id:int,payment_id:int,escrow_id:int} */
    private function makePaidOrder(string $fundsFlow = 'separate_charge_transfer', array $payment = []): array
    {
        $buyer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $listingId = (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Webhook reconciliation item',
            'description' => 'Marketplace payment reconciliation fixture.',
            'price' => 10.00,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'inventory_count' => 0,
            'status' => 'sold',
            'moderation_status' => 'approved',
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-WEBHOOK-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'marketplace_listing_id' => $listingId,
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
            'status' => 'paid',
            'payment_intent_id' => $payment['stripe_payment_intent_id'] ?? 'pi_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $defaults = [
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'stripe_payment_intent_id' => DB::table('marketplace_orders')->where('id', $orderId)->value('payment_intent_id'),
            'stripe_charge_id' => 'ch_' . uniqid(),
            'funds_flow' => $fundsFlow,
            'amount' => 10.00,
            'currency' => 'EUR',
            'platform_fee' => 0.50,
            'seller_payout' => 9.50,
            'status' => 'succeeded',
            'payout_status' => 'paid',
            'payout_id' => 'tr_' . uniqid(),
            'paid_out_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $paymentId = (int) DB::table('marketplace_payments')->insertGetId(array_merge($defaults, $payment));
        $escrowId = (int) DB::table('marketplace_escrow')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => (float) ($payment['seller_payout'] ?? 9.50),
            'currency' => 'EUR',
            'status' => 'released',
            'held_at' => now()->subDay(),
            'release_after' => now()->subHour(),
            'released_at' => now(),
            'release_trigger' => 'buyer_confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'buyer' => $buyer,
            'seller' => $seller,
            'listing_id' => $listingId,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'escrow_id' => $escrowId,
        ];
    }

    public function test_external_full_refund_reconciles_separate_charge_once(): void
    {
        $fixture = $this->makePaidOrder('separate_charge_transfer');
        $payment = DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->first();
        $refund = (object) [
            'id' => 're_separate_' . uniqid(),
            'amount' => 1000,
        ];
        $charge = (object) [
            'id' => $payment->stripe_charge_id,
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => 1000,
            'amount_refunded' => 1000,
            'refunds' => (object) ['data' => [$refund]],
        ];

        TenantContext::reset();
        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);
        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);
        $this->assertNull(TenantContext::currentId());

        $this->assertFullRefundState($fixture, $refund->id, 0.50, 9.50);
        $this->assertStripeRequestCount('/reversals', 1);
        $this->assertStripeIdempotencyKey(
            'marketplace-external-transfer-reversal-' . hash('sha256', $refund->id),
        );
    }

    public function test_external_full_refund_reconciles_destination_charge_once(): void
    {
        $fixture = $this->makePaidOrder('destination_charge');
        $payment = DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->first();
        $refund = (object) [
            'id' => 're_destination_' . uniqid(),
            'amount' => 1000,
        ];
        $charge = (object) [
            'id' => $payment->stripe_charge_id,
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => 1000,
            'amount_refunded' => 1000,
            'transfer' => 'tr_destination',
            'application_fee' => 'fee_destination',
            'refunds' => (object) ['data' => [$refund]],
        ];

        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);
        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);

        $this->assertFullRefundState($fixture, $refund->id, 0.50, 9.50);
        $this->assertStripeRequestCount('/reversals', 1);
        $this->assertStripeRequestCount('/application_fees/', 1);
        $this->assertStripeIdempotencyKey(
            'marketplace-external-transfer-reversal-' . hash('sha256', $refund->id),
        );
        $this->assertStripeIdempotencyKey(
            'marketplace-external-fee-refund-' . hash('sha256', $refund->id),
        );
    }

    public function test_external_refund_waits_for_inflight_payout_before_reversing_it(): void
    {
        $fixture = $this->makePaidOrder('separate_charge_transfer', [
            'payout_status' => 'scheduled',
            'payout_id' => null,
            'paid_out_at' => null,
        ]);
        $payment = DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->first();
        $refund = (object) [
            'id' => 're_inflight_' . uniqid(),
            'amount' => 1000,
        ];
        $charge = (object) [
            'id' => $payment->stripe_charge_id,
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => 1000,
            'amount_refunded' => 1000,
            'refunds' => (object) ['data' => [$refund]],
        ];

        try {
            MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);
            $this->fail('An in-flight payout must defer webhook refund reconciliation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(__('api.marketplace_payout_processing'), $exception->getMessage());
        }
        $this->assertDatabaseMissing('marketplace_payment_refunds', [
            'stripe_refund_id' => $refund->id,
        ]);
        $this->assertStripeRequestCount('/reversals', 0);

        DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->update([
            'payout_status' => 'paid',
            'payout_id' => 'tr_persisted_after_inflight',
            'paid_out_at' => now(),
            'updated_at' => now(),
        ]);
        MarketplacePaymentService::handleWebhookEvent('charge.refunded', $charge);

        $this->assertFullRefundState($fixture, $refund->id, 0.50, 9.50);
        $this->assertStripeRequestCount('/reversals', 1);
    }

    public function test_charge_dispute_active_then_won_restores_order_and_hold(): void
    {
        $fixture = $this->makePaidOrder('separate_charge_transfer');
        $payment = DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->first();
        DB::table('marketplace_seller_profiles')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $fixture['seller']->id,
            'seller_type' => 'private',
            'stripe_account_id' => 'acct_dispute_reimbursement',
            'stripe_onboarding_complete' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('marketplace_escrow')->where('id', $fixture['escrow_id'])->update([
            'status' => 'held',
            'released_at' => null,
            'release_trigger' => null,
        ]);
        $active = (object) [
            'id' => 'dp_won_' . uniqid(),
            'charge' => $payment->stripe_charge_id,
            'amount' => 1000,
            'status' => 'needs_response',
        ];

        MarketplacePaymentService::handleWebhookEvent('charge.dispute.created', $active);
        MarketplacePaymentService::handleWebhookEvent('charge.dispute.updated', $active);
        $this->assertDatabaseHas('marketplace_orders', ['id' => $fixture['order_id'], 'status' => 'disputed']);
        $this->assertDatabaseHas('marketplace_escrow', ['id' => $fixture['escrow_id'], 'status' => 'disputed']);

        $won = clone $active;
        $won->status = 'won';
        MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $won);
        MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $won);

        $this->assertDatabaseHas('marketplace_orders', ['id' => $fixture['order_id'], 'status' => 'paid']);
        $this->assertDatabaseHas('marketplace_escrow', ['id' => $fixture['escrow_id'], 'status' => 'held']);
        $this->assertDatabaseHas('marketplace_payments', [
            'id' => $fixture['payment_id'],
            'stripe_dispute_id' => $won->id,
            'stripe_dispute_status' => 'won',
            'dispute_previous_order_status' => 'paid',
            'status' => 'succeeded',
            'seller_payout' => 9.50,
            'payout_status' => 'paid',
        ]);
        $this->assertDatabaseHas('marketplace_payment_refunds', [
            'stripe_refund_id' => 'dispute:' . $won->id,
            'reason' => 'stripe_dispute_won',
            'seller_payout_reversal' => 9.50,
        ]);
        $this->assertStripeRequestCount('/reversals', 1);
        $this->assertStripeRequestUrlEndsWith('/v1/transfers', 1);
        $this->assertStripeIdempotencyKey(
            'marketplace-dispute-transfer-reversal-' . hash('sha256', $won->id),
        );
        $this->assertStripeIdempotencyKey(
            'marketplace-dispute-transfer-reimbursement-' . hash('sha256', $won->id),
        );
    }

    public function test_lost_charge_dispute_is_idempotent_and_restores_inventory_once(): void
    {
        $fixture = $this->makePaidOrder('destination_charge');
        $payment = DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->first();
        $lost = (object) [
            'id' => 'dp_lost_' . uniqid(),
            'charge' => $payment->stripe_charge_id,
            'amount' => 1000,
            'status' => 'lost',
        ];

        TenantContext::reset();
        MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $lost);
        MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $lost);
        $this->assertNull(TenantContext::currentId());

        $this->assertDatabaseHas('marketplace_payments', [
            'id' => $fixture['payment_id'],
            'status' => 'refunded',
            'refund_amount' => 10.00,
            'refund_reason' => 'stripe_dispute_lost',
            'platform_fee' => 0.00,
            'seller_payout' => 0.00,
            'payout_status' => 'failed',
            'stripe_dispute_status' => 'lost',
        ]);
        $this->assertDatabaseHas('marketplace_orders', ['id' => $fixture['order_id'], 'status' => 'refunded']);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $fixture['listing_id'],
            'status' => 'active',
            'inventory_count' => 1,
        ]);
        $this->assertSame(1, DB::table('marketplace_payment_refunds')
            ->where('tenant_id', $this->testTenantId)
            ->where('stripe_refund_id', 'dispute:' . $lost->id)
            ->count());
        $this->assertDatabaseHas('marketplace_payment_refunds', [
            'stripe_refund_id' => 'dispute:' . $lost->id,
            'seller_payout_reversal' => 9.50,
        ]);
        $this->assertStripeRequestCount('/reversals', 1);
        $this->assertStripeIdempotencyKey(
            'marketplace-dispute-transfer-reversal-' . hash('sha256', $lost->id),
        );
    }

    public function test_lost_dispute_reverses_payout_after_earlier_zero_reversal_hold(): void
    {
        $fixture = $this->makePaidOrder('separate_charge_transfer', [
            'payout_status' => 'pending',
            'payout_id' => null,
            'paid_out_at' => null,
        ]);
        DB::table('marketplace_escrow')->where('id', $fixture['escrow_id'])->update([
            'status' => 'held',
            'released_at' => null,
            'release_trigger' => null,
        ]);
        $payment = DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->first();
        $dispute = (object) [
            'id' => 'dp_late_payout_' . uniqid(),
            'charge' => $payment->stripe_charge_id,
            'amount' => 1000,
            'status' => 'needs_response',
        ];

        MarketplacePaymentService::handleWebhookEvent('charge.dispute.created', $dispute);
        $this->assertDatabaseHas('marketplace_payment_refunds', [
            'stripe_refund_id' => 'dispute:' . $dispute->id,
            'reason' => 'stripe_dispute_hold',
            'seller_payout_reversal' => 0,
        ]);
        $this->assertStripeRequestCount('/reversals', 0);

        DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->update([
            'payout_status' => 'scheduled',
            'updated_at' => now(),
        ]);
        $dispute->status = 'lost';
        try {
            MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $dispute);
            $this->fail('A lost dispute must wait for an in-flight payout to persist.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(__('api.marketplace_payout_processing'), $exception->getMessage());
        }
        $this->assertStripeRequestCount('/reversals', 0);

        DB::table('marketplace_payments')->where('id', $fixture['payment_id'])->update([
            'payout_status' => 'paid',
            'payout_id' => 'tr_late_dispute_payout',
            'paid_out_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('marketplace_escrow')->where('id', $fixture['escrow_id'])->update([
            'status' => 'released',
            'released_at' => now(),
            'release_trigger' => 'buyer_confirmed',
            'updated_at' => now(),
        ]);

        MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $dispute);
        MarketplacePaymentService::handleWebhookEvent('charge.dispute.closed', $dispute);

        $this->assertDatabaseHas('marketplace_payment_refunds', [
            'stripe_refund_id' => 'dispute:' . $dispute->id,
            'reason' => 'stripe_dispute_lost',
            'seller_payout_reversal' => 9.50,
        ]);
        $this->assertDatabaseHas('marketplace_payments', [
            'id' => $fixture['payment_id'],
            'status' => 'refunded',
            'seller_payout' => 0,
            'payout_status' => 'failed',
        ]);
        $this->assertStripeRequestCount('/reversals', 1);
    }

    public function test_seller_ledger_keeps_currencies_separate_and_includes_partial_refunds(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create();
        $buyer = User::factory()->forTenant($this->testTenantId)->create();
        $this->insertSellerPayment($buyer->id, $seller->id, 'EUR', 'succeeded', 'pending', 8.00);
        $this->insertSellerPayment($buyer->id, $seller->id, 'eur', 'partially_refunded', 'paid', 4.00);
        $this->insertSellerPayment($buyer->id, $seller->id, 'USD', 'succeeded', 'scheduled', 6.00);

        $balance = MarketplacePaymentService::getSellerBalance((int) $seller->id);
        $payouts = MarketplacePaymentService::getSellerPayouts((int) $seller->id);

        $this->assertNull($balance['pending']);
        $this->assertNull($balance['available']);
        $this->assertNull($balance['currency']);
        $this->assertNull($balance['total_earned']);
        $this->assertSame([
            ['currency' => 'EUR', 'pending' => 8.0, 'available' => 4.0, 'total_earned' => 12.0],
            ['currency' => 'USD', 'pending' => 0.0, 'available' => 6.0, 'total_earned' => 6.0],
        ], $balance['balances_by_currency']);
        $this->assertSame(3, $payouts['total']);
        $this->assertContains('partially_refunded', array_column($payouts['items'], 'status'));
    }

    public function test_partially_refunded_separate_charge_keeps_reduced_hold_eligible_for_release(): void
    {
        $fixture = $this->makePaidOrder('separate_charge_transfer', [
            'status' => 'partially_refunded',
            'refund_amount' => 4.00,
            'platform_fee' => 0.50,
            'seller_payout' => 5.50,
            'payout_status' => 'pending',
            'payout_id' => null,
            'paid_out_at' => null,
        ]);
        DB::table('marketplace_escrow')->where('id', $fixture['escrow_id'])->update([
            'amount' => 5.50,
            'status' => 'held',
            'released_at' => null,
            'release_trigger' => null,
        ]);
        $escrow = MarketplaceEscrow::withoutGlobalScopes()->findOrFail($fixture['escrow_id']);

        try {
            MarketplaceEscrowService::releaseFunds($escrow, 'buyer_confirmed');
            $this->fail('The fixture intentionally has no connected seller account.');
        } catch (\RuntimeException $exception) {
            // Reaching the seller-account boundary proves the reduced partial
            // refund hold passed the payment eligibility claim.
            $this->assertSame(
                __('api.marketplace_escrow_seller_payout_unavailable'),
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('marketplace_payments', [
            'id' => $fixture['payment_id'],
            'status' => 'partially_refunded',
            'seller_payout' => 5.50,
            'payout_status' => 'failed',
        ]);
        $this->assertDatabaseHas('marketplace_escrow', [
            'id' => $fixture['escrow_id'],
            'status' => 'held',
            'amount' => 5.50,
        ]);
    }

    private function assertFullRefundState(array $fixture, string $refundId, float $fee, float $payout): void
    {
        $this->assertDatabaseHas('marketplace_payment_refunds', [
            'tenant_id' => $this->testTenantId,
            'payment_id' => $fixture['payment_id'],
            'stripe_refund_id' => $refundId,
            'amount' => 10.00,
            'platform_fee_reversal' => $fee,
            'seller_payout_reversal' => $payout,
        ]);
        $this->assertSame(1, DB::table('marketplace_payment_refunds')
            ->where('tenant_id', $this->testTenantId)
            ->where('stripe_refund_id', $refundId)
            ->count());
        $this->assertDatabaseHas('marketplace_payments', [
            'id' => $fixture['payment_id'],
            'status' => 'refunded',
            'refund_amount' => 10.00,
            'platform_fee' => 0.00,
            'seller_payout' => 0.00,
            'payout_status' => 'failed',
        ]);
        $this->assertDatabaseHas('marketplace_orders', ['id' => $fixture['order_id'], 'status' => 'refunded']);
        $this->assertDatabaseHas('marketplace_escrow', [
            'id' => $fixture['escrow_id'],
            'status' => 'refunded',
            'amount' => 0.00,
        ]);
        $this->assertDatabaseHas('marketplace_listings', [
            'id' => $fixture['listing_id'],
            'status' => 'active',
            'inventory_count' => 1,
        ]);
    }

    private function assertStripeRequestCount(string $pathFragment, int $expected): void
    {
        $this->assertSame($expected, count(array_filter(
            $this->stripeHttp->requests,
            static fn (array $request): bool => str_contains($request['url'], $pathFragment),
        )));
    }

    private function assertStripeRequestUrlEndsWith(string $path, int $expected): void
    {
        $this->assertSame($expected, count(array_filter(
            $this->stripeHttp->requests,
            static fn (array $request): bool => str_ends_with($request['url'], $path),
        )));
    }

    private function assertStripeIdempotencyKey(string $expected): void
    {
        $headers = array_merge(...array_map(
            static fn (array $request): array => $request['headers'],
            $this->stripeHttp->requests,
        ));
        $this->assertContains('Idempotency-Key: ' . $expected, $headers);
    }

    private function insertSellerPayment(
        int $buyerId,
        int $sellerId,
        string $currency,
        string $status,
        string $payoutStatus,
        float $sellerPayout,
    ): void {
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'order_number' => 'MKT-BAL-' . strtoupper(uniqid('', true)),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'quantity' => 1,
            'unit_price' => $sellerPayout,
            'total_price' => $sellerPayout,
            'currency' => strtoupper($currency),
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('marketplace_payments')->insert([
            'tenant_id' => $this->testTenantId,
            'order_id' => $orderId,
            'stripe_payment_intent_id' => 'pi_bal_' . uniqid(),
            'funds_flow' => 'destination_charge',
            'amount' => $sellerPayout,
            'currency' => $currency,
            'platform_fee' => 0,
            'seller_payout' => $sellerPayout,
            'status' => $status,
            'refund_amount' => $status === 'partially_refunded' ? 1.00 : null,
            'payout_status' => $payoutStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
