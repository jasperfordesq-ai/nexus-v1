<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolDonation;
use App\Models\VolGivingDay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StripeDonationService — Stripe payment processing for monetary donations.
 *
 * Handles PaymentIntent creation, webhook event processing (succeeded, failed,
 * refunded), admin-initiated refunds, and donation receipt generation.
 *
 * All financial state changes use DB::transaction(). All Stripe API calls are
 * wrapped in try/catch with Log::error. Amounts are stored as decimals in DB
 * and sent as cents (smallest currency unit) to Stripe.
 */
class StripeDonationService
{
    /**
     * Create a Stripe PaymentIntent and a pending donation record.
     *
     * @param int   $userId   Authenticated user ID
     * @param int   $tenantId Current tenant ID
     * @param array $data     Keys: amount (decimal), currency (3-letter ISO),
     *                        giving_day_id?, opportunity_id?, community_project_id?,
     *                        message?, is_anonymous?
     * @return array{client_secret: string, donation_id: int}
     *
     * @throws \RuntimeException On Stripe API failure
     * @throws \InvalidArgumentException On validation failure
     */
    public static function createPaymentIntent(int $userId, int $tenantId, array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        $currency = strtolower(trim($data['currency'] ?? 'eur'));

        if ($amount < 0.50) {
            throw new \InvalidArgumentException('Donation amount must be at least 0.50.');
        }
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'first_name', 'last_name', 'email', 'stripe_customer_id']);

        if (!$user) {
            throw new \RuntimeException('User not found.');
        }

        $client = StripeService::client();

        // Get or create Stripe Customer
        $stripeCustomerId = $user->stripe_customer_id;

        if (empty($stripeCustomerId)) {
            try {
                $customer = $client->customers->create([
                    'email' => $user->email,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'metadata' => [
                        'nexus_user_id' => $userId,
                        'nexus_tenant_id' => $tenantId,
                    ],
                ]);
                $stripeCustomerId = $customer->id;

                DB::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->update(['stripe_customer_id' => $stripeCustomerId]);
            } catch (\Exception $e) {
                Log::error('Stripe: failed to create customer', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Failed to create Stripe customer: ' . $e->getMessage());
            }
        }

        // Build tenant name for description
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        // Create PaymentIntent
        try {
            $paymentIntent = $client->paymentIntents->create([
                'amount' => (int) round($amount * 100),
                'currency' => $currency,
                'customer' => $stripeCustomerId,
                'description' => "Donation to {$tenantName}",
                'metadata' => [
                    'nexus_tenant_id' => $tenantId,
                    'nexus_user_id' => $userId,
                    'nexus_donation_type' => 'monetary',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe: failed to create PaymentIntent', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'amount' => $amount,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create payment intent: ' . $e->getMessage());
        }

        // Create pending donation record in a transaction
        $donation = DB::transaction(function () use (
            $tenantId, $userId, $data, $amount, $currency, $paymentIntent, $user
        ) {
            return VolDonation::create([
                'user_id' => $userId,
                'opportunity_id' => isset($data['opportunity_id']) ? (int) $data['opportunity_id'] : null,
                'community_project_id' => isset($data['community_project_id']) ? (int) $data['community_project_id'] : null,
                'giving_day_id' => isset($data['giving_day_id']) ? (int) $data['giving_day_id'] : null,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'payment_method' => 'stripe',
                'payment_reference' => '',
                'donor_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'donor_email' => $user->email ?? '',
                'message' => trim($data['message'] ?? ''),
                'is_anonymous' => !empty($data['is_anonymous']) ? 1 : 0,
                'status' => 'pending',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'created_at' => now(),
            ]);
        });

        Log::info('Stripe: PaymentIntent created for donation', [
            'donation_id' => $donation->id,
            'payment_intent_id' => $paymentIntent->id,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return [
            'client_secret' => $paymentIntent->client_secret,
            'donation_id' => $donation->id,
        ];
    }

    /**
     * Handle a payment_intent.succeeded webhook event.
     *
     * Marks the donation as completed and increments giving day totals.
     * Idempotent: skips if donation is already completed.
     *
     * @param object $paymentIntent Stripe PaymentIntent object from webhook
     */
    public static function handlePaymentSucceeded(object $paymentIntent): void
    {
        $piId = $paymentIntent->id ?? null;

        if (!$piId) {
            Log::warning('Stripe donation: payment_intent.succeeded with no ID');
            return;
        }

        // Tenant scoping: derive tenant from PaymentIntent metadata when present,
        // and cross-check against the donation record to prevent cross-tenant
        // mix-ups on lookup-by-PI (PI IDs are globally unique, but defense-in-depth).
        $metaTenantId = isset($paymentIntent->metadata->nexus_tenant_id)
            ? (int) $paymentIntent->metadata->nexus_tenant_id
            : null;

        $query = DB::table('vol_donations')->where('stripe_payment_intent_id', $piId);
        if ($metaTenantId) {
            $query->where('tenant_id', $metaTenantId);
        }
        $donation = $query->first();

        if (!$donation) {
            Log::info('Stripe donation: no matching donation for PaymentIntent', [
                'payment_intent_id' => $piId,
                'meta_tenant_id' => $metaTenantId,
            ]);
            return;
        }

        // Idempotent: skip if already completed
        if ($donation->status === 'completed') {
            Log::info('Stripe donation: already completed, skipping', [
                'donation_id' => $donation->id,
                'payment_intent_id' => $piId,
            ]);
            return;
        }

        DB::transaction(function () use ($donation, $piId) {
            DB::table('vol_donations')
                ->where('id', $donation->id)
                ->where('tenant_id', $donation->tenant_id)
                ->update(['status' => 'completed']);

            // Increment giving day raised_amount if applicable
            if (!empty($donation->giving_day_id)) {
                DB::table('vol_giving_days')
                    ->where('id', $donation->giving_day_id)
                    ->where('tenant_id', $donation->tenant_id)
                    ->increment('raised_amount', (float) $donation->amount);
            }
        });

        Log::info('Stripe donation: payment succeeded', [
            'donation_id' => $donation->id,
            'payment_intent_id' => $piId,
            'amount' => $donation->amount,
        ]);
    }

    /**
     * Handle a payment_intent.payment_failed webhook event.
     *
     * Marks the donation as failed.
     *
     * @param object $paymentIntent Stripe PaymentIntent object from webhook
     */
    public static function handlePaymentFailed(object $paymentIntent): void
    {
        $piId = $paymentIntent->id ?? null;

        if (!$piId) {
            Log::warning('Stripe donation: payment_intent.payment_failed with no ID');
            return;
        }

        $metaTenantId = isset($paymentIntent->metadata->nexus_tenant_id)
            ? (int) $paymentIntent->metadata->nexus_tenant_id
            : null;

        $query = DB::table('vol_donations')->where('stripe_payment_intent_id', $piId);
        if ($metaTenantId) {
            $query->where('tenant_id', $metaTenantId);
        }
        $donation = $query->first();

        if (!$donation) {
            Log::info('Stripe donation: no matching donation for failed PaymentIntent', [
                'payment_intent_id' => $piId,
                'meta_tenant_id' => $metaTenantId,
            ]);
            return;
        }

        DB::table('vol_donations')
            ->where('id', $donation->id)
            ->where('tenant_id', $donation->tenant_id)
            ->update(['status' => 'failed']);

        Log::warning('Stripe donation: payment failed', [
            'donation_id' => $donation->id,
            'payment_intent_id' => $piId,
        ]);
    }

    /**
     * Handle a charge.refunded webhook event.
     *
     * Marks the donation as refunded and decrements giving day totals.
     *
     * @param object $charge Stripe Charge object from webhook
     */
    public static function handleChargeRefunded(object $charge): void
    {
        $piId = $charge->payment_intent ?? null;

        if (!$piId) {
            Log::warning('Stripe donation: charge.refunded with no payment_intent');
            return;
        }

        // Pull tenant from charge.metadata if Stripe copied it from the PI
        $metaTenantId = isset($charge->metadata->nexus_tenant_id)
            ? (int) $charge->metadata->nexus_tenant_id
            : null;

        $query = DB::table('vol_donations')->where('stripe_payment_intent_id', $piId);
        if ($metaTenantId) {
            $query->where('tenant_id', $metaTenantId);
        }
        $donation = $query->first();

        if (!$donation) {
            Log::info('Stripe donation: no matching donation for refunded charge', [
                'payment_intent_id' => $piId,
                'meta_tenant_id' => $metaTenantId,
            ]);
            return;
        }

        if ($donation->status === 'refunded') {
            Log::info('Stripe donation: already refunded, skipping', [
                'donation_id' => $donation->id,
            ]);
            return;
        }

        DB::transaction(function () use ($donation) {
            DB::table('vol_donations')
                ->where('id', $donation->id)
                ->where('tenant_id', $donation->tenant_id)
                ->update(['status' => 'refunded']);

            if (!empty($donation->giving_day_id)) {
                DB::table('vol_giving_days')
                    ->where('id', $donation->giving_day_id)
                    ->where('tenant_id', $donation->tenant_id)
                    ->decrement('raised_amount', (float) $donation->amount);
            }
        });

        Log::info('Stripe donation: charge refunded', [
            'donation_id' => $donation->id,
            'payment_intent_id' => $piId,
            'amount' => $donation->amount,
        ]);
    }

    /**
     * Create a refund for a completed donation (admin action).
     *
     * @param int $donationId Donation ID to refund
     * @param int $tenantId   Current tenant ID (security check)
     * @return array{success: bool, refund_id: string}
     *
     * @throws \RuntimeException On Stripe API failure or invalid state
     */
    public static function createRefund(int $donationId, int $tenantId): array
    {
        $donation = DB::table('vol_donations')
            ->where('id', $donationId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$donation) {
            throw new \RuntimeException('Donation not found.');
        }

        if ($donation->status !== 'completed') {
            throw new \RuntimeException('Only completed donations can be refunded.');
        }

        if (empty($donation->stripe_payment_intent_id)) {
            throw new \RuntimeException('Donation has no associated Stripe payment.');
        }

        $client = StripeService::client();

        try {
            $refund = $client->refunds->create([
                'payment_intent' => $donation->stripe_payment_intent_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe: failed to create refund', [
                'donation_id' => $donationId,
                'payment_intent_id' => $donation->stripe_payment_intent_id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to process refund: ' . $e->getMessage());
        }

        DB::transaction(function () use ($donation) {
            DB::table('vol_donations')
                ->where('id', $donation->id)
                ->update(['status' => 'refunded']);

            if (!empty($donation->giving_day_id)) {
                DB::table('vol_giving_days')
                    ->where('id', $donation->giving_day_id)
                    ->where('tenant_id', $donation->tenant_id)
                    ->decrement('raised_amount', (float) $donation->amount);
            }
        });

        Log::info('Stripe donation: admin refund processed', [
            'donation_id' => $donationId,
            'refund_id' => $refund->id,
            'amount' => $donation->amount,
        ]);

        return [
            'success' => true,
            'refund_id' => $refund->id,
        ];
    }

    /**
     * Get a formatted donation receipt.
     *
     * Accessible by the donor or by an admin of the same tenant.
     *
     * @param int $donationId Donation ID
     * @param int $userId     Requesting user ID
     * @param int $tenantId   Current tenant ID
     * @return array|null Receipt data or null if not found/not authorized
     */
    public static function getDonationReceipt(int $donationId, int $userId, int $tenantId): ?array
    {
        $donation = DB::table('vol_donations')
            ->where('id', $donationId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$donation) {
            return null;
        }

        // Check authorization: must be the donor or an admin
        $isOwner = (int) $donation->user_id === $userId;
        $isAdmin = false;

        if (!$isOwner) {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->first(['role', 'is_super_admin', 'is_tenant_super_admin']);

            if ($user) {
                $isAdmin = in_array($user->role ?? '', ['admin', 'tenant_admin', 'super_admin', 'god'], true)
                    || !empty($user->is_super_admin)
                    || !empty($user->is_tenant_super_admin);
            }
        }

        if (!$isOwner && !$isAdmin) {
            return null;
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        // Build donor display name
        $donorName = $donation->donor_name ?? '';
        if ($donation->is_anonymous) {
            $donorName = 'Anonymous';
        }

        return [
            'donation_id' => $donation->id,
            'donor_name' => $donorName,
            'donor_email' => $donation->is_anonymous ? null : ($donation->donor_email ?? null),
            'amount' => number_format((float) $donation->amount, 2, '.', ''),
            'currency' => $donation->currency,
            'status' => $donation->status,
            'payment_method' => $donation->payment_method,
            'payment_reference' => $donation->stripe_payment_intent_id ?? $donation->payment_reference ?? '',
            'message' => $donation->message ?? '',
            'tenant_name' => $tenantName,
            'date' => $donation->created_at,
            'is_anonymous' => (bool) $donation->is_anonymous,
        ];
    }
}
