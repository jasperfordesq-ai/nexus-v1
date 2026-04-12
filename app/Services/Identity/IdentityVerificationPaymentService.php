<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;
use App\Services\StripeService;
use App\Services\TenantSettingsService;

/**
 * IdentityVerificationPaymentService — Handles the one-time verification fee.
 *
 * Creates a Stripe PaymentIntent for the verification fee and tracks payment
 * status on identity_verification_sessions. Uses the "pay once" rule: if a
 * user has any session with payment_status='completed' for this tenant, they
 * don't pay again on retry.
 */
class IdentityVerificationPaymentService
{
    /**
     * Get the verification fee in cents for a tenant.
     * Default: 500 (€5.00). Super admin can set to 0 for free verification.
     */
    public static function getFeeCents(int $tenantId): int
    {
        return (int) app(TenantSettingsService::class)->get($tenantId, 'identity_verification_fee_cents', '500');
    }

    /**
     * Check if user has already paid for verification in this tenant.
     * The "pay once" rule — skip payment on retry after failure.
     */
    public static function hasCompletedPayment(int $tenantId, int $userId): bool
    {
        return IdentityVerificationSessionService::hasCompletedPaymentForTenant($tenantId, $userId);
    }

    /**
     * Create a Stripe PaymentIntent for the verification fee.
     *
     * @return array{client_secret: string, payment_intent_id: string}
     * @throws \RuntimeException
     */
    public static function createPaymentIntent(int $userId, int $tenantId, int $feeCents): array
    {
        if ($feeCents <= 0) {
            throw new \InvalidArgumentException('Fee must be greater than 0.');
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
                        'nexus_user_id' => (string) $userId,
                        'nexus_tenant_id' => (string) $tenantId,
                    ],
                ]);
                $stripeCustomerId = $customer->id;

                DB::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->update(['stripe_customer_id' => $stripeCustomerId]);
            } catch (\Exception $e) {
                Log::error('Stripe: failed to create customer for verification fee', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Failed to create Stripe customer.');
            }
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $currency = TenantContext::getCurrency();

        try {
            $paymentIntent = $client->paymentIntents->create([
                'amount' => $feeCents,
                'currency' => $currency,
                'customer' => $stripeCustomerId,
                'description' => "Identity Verification Fee — {$tenantName}",
                'metadata' => [
                    'nexus_type' => 'identity_verification',
                    'nexus_tenant_id' => (string) $tenantId,
                    'nexus_user_id' => (string) $userId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe: failed to create verification fee PaymentIntent', [
                'user_id' => $userId,
                'fee_cents' => $feeCents,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create payment.');
        }

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
        ];
    }

    /**
     * Handle payment_intent.succeeded webhook for verification fees.
     * Updates the session's payment_status to 'completed'.
     */
    public static function handlePaymentSucceeded(object $paymentIntent): void
    {
        $type = $paymentIntent->metadata->nexus_type ?? '';
        if ($type !== 'identity_verification') {
            return; // Not a verification fee payment
        }

        $piId = $paymentIntent->id;
        $session = IdentityVerificationSessionService::findByPaymentIntentId($piId);
        if (!$session) {
            Log::warning("Verification payment succeeded but no session found for PI: {$piId}");
            return;
        }

        IdentityVerificationSessionService::updatePaymentStatus((int) $session['id'], 'completed');
        Log::info("Verification payment completed for session {$session['id']}");
    }

    /**
     * Handle payment_intent.payment_failed webhook for verification fees.
     */
    public static function handlePaymentFailed(object $paymentIntent): void
    {
        $type = $paymentIntent->metadata->nexus_type ?? '';
        if ($type !== 'identity_verification') {
            return;
        }

        $piId = $paymentIntent->id;
        $session = IdentityVerificationSessionService::findByPaymentIntentId($piId);
        if (!$session) {
            return;
        }

        IdentityVerificationSessionService::updatePaymentStatus((int) $session['id'], 'failed');
        Log::warning("Verification payment failed for session {$session['id']}");
    }
}
