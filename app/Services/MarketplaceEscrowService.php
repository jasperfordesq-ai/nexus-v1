<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\MarketplaceEscrow;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePayment;
use App\Models\Notification;
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

        try {
            $escrow->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // UNIQUE(order_id) backstop: a concurrent holdFunds won the
            // race between our exists-check and save — return its row.
            return MarketplaceEscrow::where('order_id', $order->id)->firstOrFail();
        }

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
            // Atomic claim — the in-memory 'held' check above can race
            // (buyer confirm vs auto-release cron). Exactly one caller wins;
            // the loser throws like the fast-path check (cron catches per-item).
            $claimed = MarketplaceEscrow::query()
                ->whereKey($escrow->id)
                ->where('status', 'held')
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_trigger' => $trigger,
                ]);

            if ($claimed === 0) {
                throw new \InvalidArgumentException("Escrow is not in 'held' status. Current: released");
            }

            $escrow->refresh();

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

        // P0.2: tell the seller their payout was released — previously silent.
        // Tenant-safe (createNotification forces the recipient's tenant_id),
        // rendered in the seller's language, and fail-safe so a bell error can
        // never unwind the already-committed release.
        try {
            $order = $escrow->order;
            $sellerId = (int) ($order->seller_id ?? 0);
            if ($order && $sellerId > 0) {
                $seller = DB::table('users')
                    ->where('id', $sellerId)
                    ->where('tenant_id', $order->tenant_id)
                    ->select(['id', 'preferred_language'])
                    ->first();
                LocaleContext::withLocale($seller, function () use ($sellerId, $escrow, $order) {
                    Notification::createNotification(
                        $sellerId,
                        __('svc_notifications.marketplace_payout.payout_sent_bell', [
                            'amount' => $escrow->amount,
                            'order'  => $order->id,
                        ]),
                        '/marketplace/orders/' . $order->id,
                        'marketplace_payout'
                    );
                    \App\Services\NotificationDispatcher::fanOutPush((int) ($sellerId), 'marketplace_payout', __('svc_notifications.marketplace_payout.payout_sent_bell', [
                        'amount' => $escrow->amount,
                        'order'  => $order->id,
                    ]), '/marketplace/orders/' . $order->id);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('MarketplaceEscrow: seller payout bell failed', [
                'escrow_id' => $escrow->id,
                'error' => $e->getMessage(),
            ]);
        }
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
            // Atomic claim — the in-memory status check above can race a
            // concurrent releaseFunds() (buyer confirm / auto-release cron).
            // An unconditional save() here would overwrite 'released' with
            // 'refunded' AFTER the seller was paid: buyer refunded AND seller
            // paid out for the same order. Exactly one transition wins.
            $claimed = MarketplaceEscrow::query()
                ->whereKey($escrow->id)
                ->whereIn('status', ['held', 'disputed'])
                ->update([
                    'status' => 'refunded',
                    'released_at' => now(),
                    'release_trigger' => null,
                ]);

            if ($claimed === 0) {
                $escrow->refresh();
                throw new \InvalidArgumentException("Escrow cannot be refunded from status: {$escrow->status}");
            }

            $escrow->refresh();

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
            $previousTenantId = TenantContext::currentId();

            try {
                // Set tenant context before any scoped operations
                TenantContext::setById($escrow->tenant_id);

                // Check for open disputes on this order
                $hasDispute = DB::table('marketplace_disputes')
                    ->where('tenant_id', $escrow->tenant_id)
                    ->where('order_id', $escrow->order_id)
                    ->where('status', 'open')
                    ->exists();

                if ($hasDispute) {
                    // Mark escrow as disputed instead of releasing — conditional
                    // so a concurrent refund/release isn't clobbered back to
                    // 'disputed' by this unconditional cron write.
                    MarketplaceEscrow::query()
                        ->whereKey($escrow->id)
                        ->where('status', 'held')
                        ->update(['status' => 'disputed']);

                    Log::info('MarketplaceEscrow: auto-release blocked by open dispute', [
                        'escrow_id' => $escrow->id,
                        'order_id' => $escrow->order_id,
                    ]);
                    continue;
                }

                self::releaseFunds($escrow, 'auto_timeout');
                $count++;
            } catch (\Exception $e) {
                Log::error('MarketplaceEscrow: auto-release failed', [
                    'escrow_id' => $escrow->id,
                    'order_id' => $escrow->order_id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($previousTenantId !== null) {
                    TenantContext::setById($previousTenantId);
                } else {
                    TenantContext::reset();
                }
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
