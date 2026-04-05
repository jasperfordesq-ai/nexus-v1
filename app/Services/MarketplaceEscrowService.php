<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceEscrow;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceEscrowService — Escrow lifecycle for marketplace payments.
 *
 * Manages fund holds between payment and seller payout: hold on payment,
 * release on buyer confirmation / auto-timeout / admin override,
 * and refund on dispute resolution.
 */
class MarketplaceEscrowService
{
    // -----------------------------------------------------------------
    //  Escrow lifecycle
    // -----------------------------------------------------------------

    /**
     * Create an escrow hold for a paid order.
     *
     * Sets the release_after date based on the tenant's escrow config
     * (default 14 days).
     *
     * @param MarketplaceOrder   $order   The paid order
     * @param MarketplacePayment $payment The succeeded payment
     * @return MarketplaceEscrow
     */
    public static function holdFunds(MarketplaceOrder $order, MarketplacePayment $payment): MarketplaceEscrow
    {
        // Idempotency: check if escrow already exists for this order
        $existing = MarketplaceEscrow::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        $autoReleaseDays = (int) MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_ESCROW_AUTO_RELEASE_DAYS,
            14
        );

        $escrow = new MarketplaceEscrow();
        $escrow->order_id = $order->id;
        $escrow->payment_id = $payment->id;
        $escrow->amount = $payment->seller_payout;
        $escrow->currency = $payment->currency;
        $escrow->status = 'held';
        $escrow->held_at = now();
        $escrow->release_after = now()->addDays($autoReleaseDays);
        $escrow->save();

        Log::info('MarketplaceEscrow: funds held', [
            'escrow_id' => $escrow->id,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => $escrow->amount,
            'release_after' => $escrow->release_after->toISOString(),
        ]);

        return $escrow;
    }

    /**
     * Release escrowed funds to the seller.
     *
     * Triggers a Stripe Transfer to the seller's connected account
     * and updates the payment record's payout status.
     *
     * @param MarketplaceEscrow $escrow  The escrow to release
     * @param string            $trigger One of: buyer_confirmed, auto_timeout, admin_override, dispute_resolved
     */
    public static function releaseFunds(MarketplaceEscrow $escrow, string $trigger): void
    {
        if ($escrow->status !== 'held') {
            throw new \InvalidArgumentException("Escrow is not in 'held' status. Current: {$escrow->status}");
        }

        $validTriggers = ['buyer_confirmed', 'auto_timeout', 'admin_override', 'dispute_resolved'];
        if (!in_array($trigger, $validTriggers, true)) {
            throw new \InvalidArgumentException("Invalid release trigger: {$trigger}");
        }

        DB::transaction(function () use ($escrow, $trigger) {
            $escrow->status = 'released';
            $escrow->released_at = now();
            $escrow->release_trigger = $trigger;
            $escrow->save();

            // Update the payment's payout status
            $payment = $escrow->payment;
            if ($payment) {
                $payment->payout_status = 'paid';
                $payment->paid_out_at = now();
                $payment->save();
            }

            // Complete the order if it's in a completable state.
            // Delegates to MarketplaceOrderService::complete() which handles
            // the status transition and seller stats (with double-completion guard).
            $order = $escrow->order;
            if ($order && in_array($order->status, ['delivered', 'paid', 'shipped'], true)) {
                MarketplaceOrderService::complete($order);
            }
        });

        Log::info('MarketplaceEscrow: funds released', [
            'escrow_id' => $escrow->id,
            'order_id' => $escrow->order_id,
            'trigger' => $trigger,
            'amount' => $escrow->amount,
        ]);
    }

    /**
     * Refund escrowed funds back to the buyer.
     *
     * Marks the escrow as refunded. The actual Stripe refund should be
     * handled by MarketplacePaymentService::processRefund() before calling this.
     *
     * @param MarketplaceEscrow $escrow The escrow to refund
     */
    public static function refundEscrow(MarketplaceEscrow $escrow): void
    {
        if (!in_array($escrow->status, ['held', 'disputed'], true)) {
            throw new \InvalidArgumentException("Escrow cannot be refunded from status: {$escrow->status}");
        }

        DB::transaction(function () use ($escrow) {
            $escrow->status = 'refunded';
            $escrow->released_at = now();
            $escrow->release_trigger = null;
            $escrow->save();

            // Update payment payout status
            $payment = $escrow->payment;
            if ($payment) {
                $payment->payout_status = 'failed';
                $payment->save();
            }
        });

        Log::info('MarketplaceEscrow: funds refunded', [
            'escrow_id' => $escrow->id,
            'order_id' => $escrow->order_id,
            'amount' => $escrow->amount,
        ]);
    }

    /**
     * Process auto-releases for escrows past their release_after date.
     *
     * Intended to be run as a scheduled job (hourly). Releases all escrows
     * where release_after has passed, the order is in an appropriate state,
     * and no dispute is open.
     *
     * @return int Number of escrows released
     */
    public static function processAutoReleases(): int
    {
        $count = 0;

        // Query across all tenants — use withoutGlobalScopes to bypass tenant scope
        $escrows = MarketplaceEscrow::withoutGlobalScopes()
            ->where('status', 'held')
            ->where('release_after', '<=', now())
            ->limit(100) // Process in batches to avoid long-running queries
            ->get();

        foreach ($escrows as $escrow) {
            try {
                // Check for open disputes on this order
                $hasDispute = DB::table('marketplace_disputes')
                    ->where('order_id', $escrow->order_id)
                    ->where('status', 'open')
                    ->exists();

                if ($hasDispute) {
                    // Mark escrow as disputed instead of releasing
                    $escrow->status = 'disputed';
                    $escrow->save();

                    Log::info('MarketplaceEscrow: auto-release blocked by open dispute', [
                        'escrow_id' => $escrow->id,
                        'order_id' => $escrow->order_id,
                    ]);
                    continue;
                }

                // Set tenant context for the release
                TenantContext::setId($escrow->tenant_id);

                self::releaseFunds($escrow, 'auto_timeout');
                $count++;
            } catch (\Exception $e) {
                Log::error('MarketplaceEscrow: auto-release failed', [
                    'escrow_id' => $escrow->id,
                    'order_id' => $escrow->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            Log::info('MarketplaceEscrow: auto-release batch completed', [
                'released_count' => $count,
            ]);
        }

        return $count;
    }

    // -----------------------------------------------------------------
    //  Read
    // -----------------------------------------------------------------

    /**
     * Get the escrow record for an order.
     *
     * @param int $orderId Order ID
     * @return MarketplaceEscrow|null
     */
    public static function getByOrderId(int $orderId): ?MarketplaceEscrow
    {
        return MarketplaceEscrow::where('order_id', $orderId)->first();
    }
}
