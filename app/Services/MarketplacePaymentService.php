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
use App\Models\MarketplaceSellerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplacePaymentService — Stripe Connect integration for the marketplace.
 *
 * Handles: Connect account creation, onboarding, PaymentIntent creation with
 * application fees and transfer_data, payment confirmation, refunds, and
 * webhook event processing.
 *
 * All financial state changes use DB::transaction(). Amounts stored as decimals
 * in the DB and sent as cents (smallest currency unit) to Stripe.
 */
class MarketplacePaymentService
{
    // -----------------------------------------------------------------
    //  Stripe Connect — Account management
    // -----------------------------------------------------------------

    /**
     * Create a Stripe Connect Express account for a seller.
     *
     * @param int $userId Authenticated user ID
     * @return array{account_id: string, onboarding_url: string}
     *
     * @throws \RuntimeException On Stripe API failure or missing seller profile
     */
    public static function createConnectAccount(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $sellerProfile = MarketplaceSellerProfile::where('user_id', $userId)->first();

        if (!$sellerProfile) {
            throw new \RuntimeException('Seller profile not found. Create a seller profile first.');
        }

        // If they already have a Connect account, return onboarding link instead
        if (!empty($sellerProfile->stripe_account_id)) {
            $onboardingUrl = self::getOnboardingLink($userId);
            return [
                'account_id' => $sellerProfile->stripe_account_id,
                'onboarding_url' => $onboardingUrl,
            ];
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'first_name', 'last_name', 'email']);

        if (!$user) {
            throw new \RuntimeException('User not found.');
        }

        $client = StripeService::client();

        try {
            $account = $client->accounts->create([
                'type' => 'express',
                'email' => $user->email,
                'metadata' => [
                    'nexus_user_id' => (string) $userId,
                    'nexus_tenant_id' => (string) $tenantId,
                ],
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MarketplacePayment: failed to create Connect account', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create Stripe Connect account: ' . $e->getMessage());
        }

        // Store the account ID on the seller profile
        $sellerProfile->stripe_account_id = $account->id;
        $sellerProfile->stripe_onboarding_complete = false;
        $sellerProfile->save();

        // Generate onboarding link
        $onboardingUrl = self::generateOnboardingLink($account->id);

        Log::info('MarketplacePayment: Connect account created', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'account_id' => $account->id,
        ]);

        return [
            'account_id' => $account->id,
            'onboarding_url' => $onboardingUrl,
        ];
    }

    /**
     * Get a Stripe onboarding URL for an incomplete Connect account.
     *
     * @param int $userId Authenticated user ID
     * @return string|null Onboarding URL, or null if already onboarded
     *
     * @throws \RuntimeException On Stripe API failure
     */
    public static function getOnboardingLink(int $userId): ?string
    {
        $sellerProfile = MarketplaceSellerProfile::where('user_id', $userId)->first();

        if (!$sellerProfile || empty($sellerProfile->stripe_account_id)) {
            return null;
        }

        if ($sellerProfile->stripe_onboarding_complete) {
            return null;
        }

        return self::generateOnboardingLink($sellerProfile->stripe_account_id);
    }

    /**
     * Check if a seller's Stripe Connect account is fully onboarded.
     *
     * @param int $userId Authenticated user ID
     * @return array{onboarded: bool, details_submitted: bool, charges_enabled: bool, payouts_enabled: bool}
     */
    public static function checkOnboardingStatus(int $userId): array
    {
        $sellerProfile = MarketplaceSellerProfile::where('user_id', $userId)->first();

        if (!$sellerProfile || empty($sellerProfile->stripe_account_id)) {
            return [
                'onboarded' => false,
                'details_submitted' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
            ];
        }

        $client = StripeService::client();

        try {
            $account = $client->accounts->retrieve($sellerProfile->stripe_account_id);
        } catch (\Exception $e) {
            Log::error('MarketplacePayment: failed to retrieve Connect account', [
                'user_id' => $userId,
                'account_id' => $sellerProfile->stripe_account_id,
                'error' => $e->getMessage(),
            ]);
            return [
                'onboarded' => (bool) $sellerProfile->stripe_onboarding_complete,
                'details_submitted' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
            ];
        }

        $isOnboarded = $account->details_submitted
            && $account->charges_enabled
            && $account->payouts_enabled;

        // Update seller profile if status changed
        if ($isOnboarded && !$sellerProfile->stripe_onboarding_complete) {
            $sellerProfile->stripe_onboarding_complete = true;
            $sellerProfile->save();

            Log::info('MarketplacePayment: seller onboarding complete', [
                'user_id' => $userId,
                'account_id' => $sellerProfile->stripe_account_id,
            ]);
        }

        return [
            'onboarded' => $isOnboarded,
            'details_submitted' => (bool) $account->details_submitted,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
        ];
    }

    // -----------------------------------------------------------------
    //  Payment creation & confirmation
    // -----------------------------------------------------------------

    /**
     * Create a Stripe PaymentIntent for a marketplace order.
     *
     * Uses Stripe Connect with application_fee_amount and transfer_data
     * to route the platform fee to the platform account and the remainder
     * to the seller's connected account.
     *
     * @param MarketplaceOrder $order Order to create payment for
     * @return array{client_secret: string, payment_intent_id: string}
     *
     * @throws \RuntimeException On missing seller account or Stripe API failure
     * @throws \InvalidArgumentException On invalid order state
     */
    public static function createPaymentIntent(MarketplaceOrder $order): array
    {
        if ($order->status !== 'pending_payment') {
            throw new \InvalidArgumentException('Order must be in pending_payment status to create a payment intent.');
        }

        $tenantId = TenantContext::getId();

        // Get seller's Stripe Connect account
        $sellerProfile = MarketplaceSellerProfile::where('user_id', $order->seller_id)->first();

        if (!$sellerProfile || empty($sellerProfile->stripe_account_id)) {
            throw new \RuntimeException('Seller has not completed Stripe onboarding.');
        }

        if (!$sellerProfile->stripe_onboarding_complete) {
            throw new \RuntimeException('Seller Stripe account onboarding is not complete.');
        }

        // Calculate fees from config
        $feePercent = (float) MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_PLATFORM_FEE_PERCENT,
            5
        );

        $totalAmount = (float) $order->total_price;
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        $amountCents = (int) round($totalAmount * 100);
        $feeCents = (int) round($platformFee * 100);

        $currency = strtolower($order->currency ?? TenantContext::getCurrency());

        $client = StripeService::client();

        try {
            // Idempotency key binds a network retry of this create() to the same
            // PaymentIntent, so a dropped connection / client retry can't end up
            // charging the buyer twice. Scoped to order id + tenant to avoid
            // collisions across tenants that share a Stripe account.
            $idempotencyKey = "market-order-{$tenantId}-{$order->id}";
            $paymentIntent = $client->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'application_fee_amount' => $feeCents,
                'transfer_data' => [
                    'destination' => $sellerProfile->stripe_account_id,
                ],
                'metadata' => [
                    'nexus_tenant_id' => (string) $tenantId,
                    'nexus_order_id' => (string) $order->id,
                    'nexus_buyer_id' => (string) $order->buyer_id,
                    'nexus_seller_id' => (string) $order->seller_id,
                    'nexus_type' => 'marketplace',
                ],
                'description' => "Marketplace order {$order->order_number}",
            ], ['idempotency_key' => $idempotencyKey]);
        } catch (\Exception $e) {
            Log::error('MarketplacePayment: failed to create PaymentIntent', [
                'order_id' => $order->id,
                'tenant_id' => $tenantId,
                'amount' => $totalAmount,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create payment intent: ' . $e->getMessage());
        }

        // Store the payment intent ID on the order for reference
        $order->payment_intent_id = $paymentIntent->id;
        $order->save();

        Log::info('MarketplacePayment: PaymentIntent created', [
            'order_id' => $order->id,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $totalAmount,
            'platform_fee' => $platformFee,
            'seller_payout' => $sellerPayout,
        ]);

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
        ];
    }

    /**
     * Confirm a payment after frontend Stripe.js completes the PaymentIntent.
     *
     * Creates a MarketplacePayment record and transitions the order to 'paid'.
     * If escrow is enabled, also creates an escrow hold via MarketplaceEscrowService.
     *
     * @param string $paymentIntentId Stripe PaymentIntent ID
     * @return MarketplacePayment
     *
     * @throws \RuntimeException On Stripe API failure or missing order
     */
    public static function confirmPayment(string $paymentIntentId): MarketplacePayment
    {
        $tenantId = TenantContext::getId();

        // Retrieve the PaymentIntent from Stripe to verify status
        $client = StripeService::client();

        try {
            $paymentIntent = $client->paymentIntents->retrieve($paymentIntentId);
        } catch (\Exception $e) {
            Log::error('MarketplacePayment: failed to retrieve PaymentIntent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to verify payment status: ' . $e->getMessage());
        }

        if ($paymentIntent->status !== 'succeeded') {
            throw new \RuntimeException("Payment has not succeeded. Current status: {$paymentIntent->status}");
        }

        // Find the order by payment_intent_id
        $orderId = (int) ($paymentIntent->metadata->nexus_order_id ?? 0);
        $order = MarketplaceOrder::find($orderId);

        if (!$order) {
            throw new \RuntimeException('Order not found for this payment intent.');
        }

        // Idempotency: check if payment already recorded (scoped by tenant)
        $existingPayment = MarketplacePayment::where('stripe_payment_intent_id', $paymentIntentId)
            ->where('tenant_id', $tenantId)
            ->first();
        if ($existingPayment) {
            return $existingPayment;
        }

        // Calculate fees
        $feePercent = (float) MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_PLATFORM_FEE_PERCENT,
            5
        );
        $totalAmount = (float) ($paymentIntent->amount / 100);
        $platformFee = round($totalAmount * ($feePercent / 100), 2);
        $sellerPayout = round($totalAmount - $platformFee, 2);

        // Get charge ID if available
        $chargeId = null;
        if (!empty($paymentIntent->latest_charge)) {
            $chargeId = is_string($paymentIntent->latest_charge)
                ? $paymentIntent->latest_charge
                : ($paymentIntent->latest_charge->id ?? null);
        }

        $payment = DB::transaction(function () use (
            $order, $paymentIntentId, $chargeId, $totalAmount,
            $platformFee, $sellerPayout, $paymentIntent, $tenantId
        ) {
            $payment = new MarketplacePayment();
            $payment->tenant_id = $tenantId;
            $payment->order_id = $order->id;
            $payment->stripe_payment_intent_id = $paymentIntentId;
            $payment->stripe_charge_id = $chargeId;
            $payment->amount = $totalAmount;
            $payment->currency = strtoupper($paymentIntent->currency ?? TenantContext::getCurrency());
            $payment->platform_fee = $platformFee;
            $payment->seller_payout = $sellerPayout;
            $payment->payment_method = $paymentIntent->payment_method_types[0] ?? 'card';
            $payment->status = 'succeeded';
            $payment->payout_status = 'pending';
            $payment->save();

            // Transition order to 'paid'
            $order->status = 'paid';
            $order->save();

            return $payment;
        });

        // Create escrow hold if escrow is enabled
        $escrowEnabled = (bool) MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_ESCROW_ENABLED,
            false
        );

        if ($escrowEnabled) {
            try {
                MarketplaceEscrowService::holdFunds($order, $payment);
            } catch (\Exception $e) {
                Log::error('MarketplacePayment: failed to create escrow hold', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                // Payment still succeeded — escrow is supplementary
            }
        }

        Log::info('MarketplacePayment: payment confirmed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'payment_intent_id' => $paymentIntentId,
            'amount' => $totalAmount,
        ]);

        return $payment;
    }

    // -----------------------------------------------------------------
    //  Refunds
    // -----------------------------------------------------------------

    /**
     * Process a full or partial refund for a marketplace order.
     *
     * @param MarketplaceOrder $order   Order to refund
     * @param float|null       $amount  Partial refund amount (null = full refund)
     * @param string           $reason  Reason for the refund
     * @return MarketplacePayment Updated payment record
     *
     * @throws \RuntimeException On Stripe API failure or invalid state
     */
    public static function processRefund(MarketplaceOrder $order, ?float $amount, string $reason): MarketplacePayment
    {
        $payment = MarketplacePayment::where('order_id', $order->id)
            ->where('status', 'succeeded')
            ->first();

        if (!$payment) {
            throw new \RuntimeException('No successful payment found for this order.');
        }

        if (empty($payment->stripe_payment_intent_id)) {
            throw new \RuntimeException('Payment has no associated Stripe payment intent.');
        }

        $refundAmount = $amount ?? (float) $payment->amount;

        if ($refundAmount <= 0 || $refundAmount > (float) $payment->amount) {
            throw new \InvalidArgumentException('Invalid refund amount.');
        }

        $client = StripeService::client();

        $refundParams = [
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => (int) round($refundAmount * 100),
            'reason' => 'requested_by_customer',
            'metadata' => [
                'nexus_order_id' => (string) $order->id,
                'nexus_reason' => $reason,
            ],
        ];

        // For Connect refunds, reverse the application fee proportionally
        $refundParams['refund_application_fee'] = true;
        $refundParams['reverse_transfer'] = true;

        try {
            $refund = $client->refunds->create($refundParams);
        } catch (\Exception $e) {
            Log::error('MarketplacePayment: failed to create refund', [
                'order_id' => $order->id,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount' => $refundAmount,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to process refund: ' . $e->getMessage());
        }

        $isFullRefund = abs($refundAmount - (float) $payment->amount) < 0.01;

        // Compensating ledger reversal: compute the proportional platform fee
        // being reversed on this refund. Stripe reverses the application_fee via
        // refund_application_fee=true above — we mirror that on our local books
        // so reports/payouts don't double-count the fee as revenue.
        $originalAmount = (float) $payment->amount;
        $originalPlatformFee = (float) $payment->platform_fee;
        $originalSellerPayout = (float) $payment->seller_payout;
        $feeReversal = $originalAmount > 0
            ? round($originalPlatformFee * ($refundAmount / $originalAmount), 2)
            : 0.0;
        $payoutReversal = round($refundAmount - $feeReversal, 2);

        DB::transaction(function () use (
            $payment, $order, $refundAmount, $reason, $isFullRefund,
            $originalPlatformFee, $originalSellerPayout, $feeReversal, $payoutReversal
        ) {
            $payment->refund_amount = ($payment->refund_amount ?? 0) + $refundAmount;
            $payment->refund_reason = $reason;
            $payment->refunded_at = now();
            $payment->status = $isFullRefund ? 'refunded' : 'partially_refunded';
            // Reverse platform fee + seller payout proportionally on the local
            // books so accounting reflects the refund (Stripe side is handled
            // via refund_application_fee=true / reverse_transfer=true).
            $payment->platform_fee = $isFullRefund
                ? 0
                : max(0, round($originalPlatformFee - $feeReversal, 2));
            $payment->seller_payout = $isFullRefund
                ? 0
                : max(0, round($originalSellerPayout - $payoutReversal, 2));
            $payment->save();

            if ($isFullRefund) {
                $order->status = 'refunded';
                $order->save();

                // Refund escrow if held
                $escrow = MarketplaceEscrow::where('order_id', $order->id)
                    ->where('status', 'held')
                    ->first();

                if ($escrow) {
                    MarketplaceEscrowService::refundEscrow($escrow);
                }
            }
        });

        // Audit trail for the compensating fee reversal — structured log acts as
        // the ledger entry until a dedicated marketplace_ledger table exists.
        Log::info('MarketplacePayment: platform fee reversed on refund', [
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'tenant_id' => $payment->tenant_id,
            'refund_amount' => $refundAmount,
            'platform_fee_reversal' => $feeReversal,
            'seller_payout_reversal' => $payoutReversal,
            'full_refund' => $isFullRefund,
        ]);

        Log::info('MarketplacePayment: refund processed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'refund_amount' => $refundAmount,
            'full_refund' => $isFullRefund,
        ]);

        return $payment->fresh();
    }

    // -----------------------------------------------------------------
    //  Webhook handlers
    // -----------------------------------------------------------------

    /**
     * Handle Stripe webhook events relevant to marketplace payments.
     *
     * Called from StripeWebhookController for marketplace-related events.
     *
     * @param string $eventType  Stripe event type
     * @param object $eventData  Stripe event data object
     */
    public static function handleWebhookEvent(string $eventType, object $eventData): void
    {
        match ($eventType) {
            'payment_intent.succeeded' => self::handlePaymentIntentSucceeded($eventData),
            'charge.refunded' => self::handleChargeRefunded($eventData),
            'account.updated' => self::handleAccountUpdated($eventData),
            default => null,
        };
    }

    /**
     * Handle payment_intent.succeeded — confirm marketplace payment.
     */
    private static function handlePaymentIntentSucceeded(object $paymentIntent): void
    {
        $nexusType = $paymentIntent->metadata->nexus_type ?? null;

        if ($nexusType !== 'marketplace') {
            return; // Not a marketplace payment, skip
        }

        $piId = $paymentIntent->id ?? null;
        if (!$piId) {
            return;
        }

        // Check if already recorded (idempotency)
        $existing = MarketplacePayment::withoutGlobalScopes()
            ->where('stripe_payment_intent_id', $piId)
            ->first();

        if ($existing && $existing->status === 'succeeded') {
            Log::info('MarketplacePayment webhook: payment already confirmed', [
                'payment_intent_id' => $piId,
            ]);
            return;
        }

        // SECURITY: Do NOT trust tenant_id from webhook metadata — an attacker
        // controlling a PaymentIntent could manipulate metadata to pivot into other
        // tenants. Resolve tenant_id from our own DB record keyed on the Stripe
        // payment_intent_id (which Stripe controls and we cannot forge).
        $paymentRecord = MarketplacePayment::withoutGlobalScopes()
            ->where('stripe_payment_intent_id', $piId)
            ->first();

        if (!$paymentRecord) {
            Log::warning('MarketplacePayment webhook: no local payment record for PI — rejecting event', [
                'payment_intent_id' => $piId,
            ]);
            return;
        }

        $orderId = (int) $paymentRecord->order_id;
        $tenantId = (int) $paymentRecord->tenant_id;

        if (!$orderId || !$tenantId) {
            Log::warning('MarketplacePayment webhook: local record missing order/tenant — rejecting event', [
                'payment_intent_id' => $piId,
                'payment_id' => $paymentRecord->id ?? null,
            ]);
            return;
        }

        // Set tenant context from our trusted DB record (never from Stripe metadata)
        TenantContext::setId($tenantId);

        try {
            self::confirmPayment($piId);
        } catch (\Exception $e) {
            Log::error('MarketplacePayment webhook: confirmPayment failed', [
                'payment_intent_id' => $piId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle charge.refunded — update marketplace payment record.
     */
    private static function handleChargeRefunded(object $charge): void
    {
        $piId = $charge->payment_intent ?? null;
        if (!$piId) {
            return;
        }

        $payment = MarketplacePayment::withoutGlobalScopes()
            ->where('stripe_payment_intent_id', $piId)
            ->first();

        if (!$payment) {
            return; // Not a marketplace payment
        }

        if (in_array($payment->status, ['refunded'], true)) {
            return; // Already processed
        }

        $refundedAmountCents = $charge->amount_refunded ?? 0;
        $refundedAmount = $refundedAmountCents / 100;
        $isFullRefund = $refundedAmountCents >= ($charge->amount ?? 0);

        $payment->refund_amount = $refundedAmount;
        $payment->refunded_at = now();
        $payment->status = $isFullRefund ? 'refunded' : 'partially_refunded';
        $payment->save();

        if ($isFullRefund) {
            $order = MarketplaceOrder::withoutGlobalScopes()->find($payment->order_id);
            if ($order && !in_array($order->status, ['refunded', 'cancelled'], true)) {
                $order->status = 'refunded';
                $order->save();
            }
        }

        Log::info('MarketplacePayment webhook: charge refunded', [
            'payment_id' => $payment->id,
            'payment_intent_id' => $piId,
            'refunded_amount' => $refundedAmount,
        ]);
    }

    /**
     * Handle account.updated — check if Connect onboarding is complete.
     */
    private static function handleAccountUpdated(object $account): void
    {
        $accountId = $account->id ?? null;
        if (!$accountId) {
            return;
        }

        $sellerProfile = MarketplaceSellerProfile::withoutGlobalScopes()
            ->where('stripe_account_id', $accountId)
            ->first();

        if (!$sellerProfile) {
            return; // Not one of our marketplace sellers
        }

        $isOnboarded = ($account->details_submitted ?? false)
            && ($account->charges_enabled ?? false)
            && ($account->payouts_enabled ?? false);

        if ($isOnboarded && !$sellerProfile->stripe_onboarding_complete) {
            $sellerProfile->stripe_onboarding_complete = true;
            $sellerProfile->save();

            Log::info('MarketplacePayment webhook: seller onboarding complete via webhook', [
                'user_id' => $sellerProfile->user_id,
                'account_id' => $accountId,
            ]);
        }
    }

    // -----------------------------------------------------------------
    //  Read — Seller payout & balance queries
    // -----------------------------------------------------------------

    /**
     * Get payout history for a seller.
     *
     * @param int $userId Seller's user ID
     * @param int $limit  Max results
     * @param int $offset Offset for pagination
     * @return array{items: array, total: int}
     */
    public static function getSellerPayouts(int $userId, int $limit = 20, int $offset = 0): array
    {
        $query = MarketplacePayment::whereHas('order', function ($q) use ($userId) {
            $q->where('seller_id', $userId);
        })->where('status', 'succeeded');

        $total = $query->count();

        $items = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function (MarketplacePayment $p) {
                return [
                    'id' => $p->id,
                    'order_id' => $p->order_id,
                    'amount' => $p->amount,
                    'platform_fee' => $p->platform_fee,
                    'seller_payout' => $p->seller_payout,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'payout_status' => $p->payout_status,
                    'payout_id' => $p->payout_id,
                    'paid_out_at' => $p->paid_out_at?->toISOString(),
                    'created_at' => $p->created_at?->toISOString(),
                ];
            })
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get the seller's pending balance (total of payments not yet paid out).
     *
     * @param int $userId Seller's user ID
     * @return array{pending: float, available: float, currency: string, total_earned: float}
     */
    public static function getSellerBalance(int $userId): array
    {
        $baseQuery = MarketplacePayment::whereHas('order', function ($q) use ($userId) {
            $q->where('seller_id', $userId);
        })->where('status', 'succeeded');

        $pending = (clone $baseQuery)
            ->where('payout_status', 'pending')
            ->sum('seller_payout');

        $available = (clone $baseQuery)
            ->whereIn('payout_status', ['scheduled', 'paid'])
            ->sum('seller_payout');

        $totalEarned = (clone $baseQuery)->sum('seller_payout');

        return [
            'pending' => round((float) $pending, 2),
            'available' => round((float) $available, 2),
            'currency' => 'EUR', // TODO: derive from seller's actual payment currency — global platform
            'total_earned' => round((float) $totalEarned, 2),
        ];
    }

    // -----------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------

    /**
     * Generate a Stripe Account Link for onboarding.
     */
    private static function generateOnboardingLink(string $accountId): string
    {
        $client = StripeService::client();

        $frontendBase = rtrim(env('REACT_FRONTEND_URL', env('APP_URL', 'https://app.project-nexus.ie')), '/');

        try {
            $accountLink = $client->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $frontendBase . '/marketplace/seller/onboard?refresh=1',
                'return_url' => $frontendBase . '/marketplace/seller/onboard?complete=1',
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;
        } catch (\Exception $e) {
            Log::error('MarketplacePayment: failed to generate onboarding link', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to generate onboarding link: ' . $e->getMessage());
        }
    }
}
