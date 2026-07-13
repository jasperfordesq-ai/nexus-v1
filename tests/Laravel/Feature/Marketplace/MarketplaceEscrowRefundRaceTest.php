<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Marketplace;

use App\Core\TenantContext;
use App\Models\MarketplaceEscrow;
use App\Models\MarketplaceOrder;
use App\Services\MarketplaceEscrowService;
use App\Services\MarketplaceOrderService;
use App\Services\MarketplacePaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * refundEscrow() used an unconditional $escrow->save() after an in-memory
 * status check — racing a concurrent releaseFunds() (buyer confirm or the
 * auto-release cron) could overwrite 'released' with 'refunded' AFTER the
 * seller payout committed: buyer refunded AND seller paid for one order.
 * The fix claims the transition atomically (WHERE status IN held/disputed),
 * mirroring releaseFunds().
 */
class MarketplaceEscrowRefundRaceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    private function makeEscrow(string $status = 'held'): MarketplaceEscrow
    {
        $orderId = (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'order_number' => 'ORD-' . strtoupper(uniqid('', true)),
            'buyer_id' => 990001,
            'seller_id' => 990002,
            'quantity' => 1,
            'unit_price' => 45.00,
            'total_price' => 45.00,
            'currency' => 'EUR',
            'status' => 'delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = (int) DB::table('marketplace_payments')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'order_id' => $orderId,
            'stripe_payment_intent_id' => 'pi_escrow_race_' . uniqid('', true),
            'amount' => 45.00,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'payout_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $escrowId = (int) DB::table('marketplace_escrow')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => 45.00,
            'currency' => 'EUR',
            'status' => $status,
            'held_at' => now(),
            'release_after' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return MarketplaceEscrow::query()->findOrFail($escrowId);
    }

    public function test_refund_succeeds_from_held_and_fails_payment_payout(): void
    {
        $escrow = $this->makeEscrow('held');

        MarketplaceEscrowService::refundEscrow($escrow);

        $this->assertSame(
            'refunded',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status')
        );
        $this->assertSame(
            'failed',
            DB::table('marketplace_payments')->where('id', $escrow->payment_id)->value('payout_status')
        );
    }

    public function test_refund_succeeds_from_disputed(): void
    {
        $escrow = $this->makeEscrow('disputed');

        MarketplaceEscrowService::refundEscrow($escrow);

        $this->assertSame(
            'refunded',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status')
        );
    }

    public function test_refund_losing_race_to_release_throws_and_does_not_clobber(): void
    {
        // The caller (processRefund) loaded the escrow while it was 'held'…
        $escrow = $this->makeEscrow('held');

        // …but a concurrent releaseFunds() (buyer confirm / auto-release cron)
        // wins the race and pays the seller out before the refund write lands.
        DB::table('marketplace_escrow')->where('id', $escrow->id)->update([
            'status' => 'released',
            'release_trigger' => 'buyer_confirmed',
            'released_at' => now(),
        ]);
        DB::table('marketplace_payments')->where('id', $escrow->payment_id)->update([
            'payout_status' => 'paid',
            'paid_out_at' => now(),
        ]);

        try {
            MarketplaceEscrowService::refundEscrow($escrow);
            $this->fail('Expected refundEscrow to refuse refunding an already-released escrow.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('payout', $e->getMessage());
        }

        // Old code overwrote the released state — both sides of the money paid.
        $this->assertSame(
            'released',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status'),
            'A lost refund race must not clobber a committed release.'
        );
        $this->assertSame(
            'paid',
            DB::table('marketplace_payments')->where('id', $escrow->payment_id)->value('payout_status'),
            'The seller payout record must survive a lost refund race.'
        );
    }

    public function test_auto_release_dispute_mark_does_not_clobber_concurrent_refund(): void
    {
        // An escrow the cron loaded as 'held', then a concurrent refund won.
        $escrow = $this->makeEscrow('refunded');
        $stale = MarketplaceEscrow::query()->findOrFail($escrow->id);
        $stale->status = 'held'; // simulate the cron's stale in-memory copy

        // The conditional disputed-mark must not flip a refunded escrow.
        MarketplaceEscrow::query()
            ->whereKey($stale->id)
            ->where('status', 'held')
            ->update(['status' => 'disputed']);

        $this->assertSame(
            'refunded',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status')
        );
    }

    public function test_payment_refund_refuses_a_payout_claimed_for_release(): void
    {
        $escrow = $this->makeEscrow('held');
        DB::table('marketplace_payments')->where('id', $escrow->payment_id)->update([
            'funds_flow' => 'separate_charge_transfer',
            'payout_status' => 'scheduled',
            'stripe_charge_id' => 'ch_release_in_flight',
        ]);
        $order = MarketplaceOrder::withoutGlobalScopes()->findOrFail($escrow->order_id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(__('api.marketplace_payout_processing'));
        MarketplacePaymentService::processRefund($order, null, 'release race');
    }

    public function test_auto_release_is_blocked_while_dispute_is_under_review(): void
    {
        $escrow = $this->makeEscrow('held');
        DB::table('marketplace_escrow')->where('id', $escrow->id)->update([
            'release_after' => now()->subMinute(),
        ]);
        DB::table('marketplace_orders')->where('id', $escrow->order_id)->update([
            'auto_complete_at' => now()->subMinute(),
        ]);
        DB::table('marketplace_disputes')->insert([
            'tenant_id' => self::TENANT_ID,
            'order_id' => $escrow->order_id,
            'opened_by' => 990001,
            'reason' => 'not_received',
            'description' => 'The delivery is still under investigation.',
            'status' => 'under_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarketplaceEscrowService::processAutoReleases();

        $this->assertSame(
            'held',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status'),
        );
        $this->assertSame(
            'pending',
            DB::table('marketplace_payments')->where('id', $escrow->payment_id)->value('payout_status'),
        );
    }

    public function test_auto_release_does_not_treat_payment_age_as_delivery_evidence(): void
    {
        $escrow = $this->makeEscrow('held');
        DB::table('marketplace_escrow')->where('id', $escrow->id)->update([
            'release_after' => now()->subMinute(),
        ]);
        DB::table('marketplace_orders')->where('id', $escrow->order_id)->update([
            'status' => 'paid',
            'auto_complete_at' => now()->subMinute(),
        ]);

        $released = MarketplaceEscrowService::processAutoReleases();

        $this->assertSame(0, $released);
        $this->assertSame(
            'held',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status'),
        );
        $this->assertSame(
            'pending',
            DB::table('marketplace_payments')->where('id', $escrow->payment_id)->value('payout_status'),
        );
    }

    public function test_delivery_confirmation_starts_dispute_window_without_immediate_payout(): void
    {
        $escrow = $this->makeEscrow('held');
        DB::table('marketplace_escrow')->where('id', $escrow->id)->update([
            'release_after' => now()->subMinute(),
        ]);
        DB::table('marketplace_orders')->where('id', $escrow->order_id)->update([
            'status' => 'shipped',
            'auto_complete_at' => null,
        ]);
        $order = MarketplaceOrder::withoutGlobalScopes()->findOrFail($escrow->order_id);

        $confirmed = MarketplaceOrderService::confirmDelivery($order);
        $released = MarketplaceEscrowService::processAutoReleases();

        $this->assertSame('delivered', $confirmed->status);
        $this->assertTrue($confirmed->auto_complete_at->isFuture());
        $this->assertSame(0, $released);
        $this->assertSame(
            'held',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status'),
        );
    }

    public function test_completion_does_not_claim_held_escrow_as_released(): void
    {
        $escrow = $this->makeEscrow('held');
        DB::table('marketplace_orders')->where('id', $escrow->order_id)->update([
            'auto_complete_at' => now()->subMinute(),
        ]);
        $order = MarketplaceOrder::withoutGlobalScopes()->findOrFail($escrow->order_id);

        $result = MarketplaceOrderService::complete($order);

        $this->assertSame('delivered', $result->status);
        $this->assertNull($result->escrow_released_at);
        $this->assertSame(
            'held',
            DB::table('marketplace_escrow')->where('id', $escrow->id)->value('status'),
        );
    }
}
