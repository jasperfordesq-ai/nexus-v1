<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\RegionalAnalytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AG59 — Stripe billing wrapper for the regional analytics product.
 *
 * Responsibilities are intentionally minimal — it manages the lifecycle of
 * the Stripe subscription that backs a regional_analytics_subscriptions row,
 * and exposes webhook handlers for invoice.paid / customer.subscription.updated.
 *
 * The actual Stripe SDK calls are wrapped in `class_exists` guards so this
 * service is safe to invoke even before composer requires stripe/stripe-php
 * (it gracefully no-ops with a logged warning, exactly like other Stripe
 * touch-points elsewhere in the codebase).
 */
class RegionalAnalyticsBilling
{
    /**
     * Create or attach a Stripe subscription for a paid plan tier.
     * Returns the stripe_subscription_id (or null when SDK absent).
     */
    public function createSubscription(int $subscriptionId, string $planTier, string $customerEmail): ?string
    {
        if (! class_exists(\Stripe\Stripe::class)) {
            Log::warning('RegionalAnalyticsBilling: Stripe SDK not installed, no-op.');
            return null;
        }

        try {
            $secret = (string) config('services.stripe.secret', env('STRIPE_SECRET_KEY', ''));
            if ($secret === '') {
                return null;
            }
            \Stripe\Stripe::setApiKey($secret);

            $priceId = match ($planTier) {
                'pro' => (string) env('STRIPE_REGIONAL_ANALYTICS_PRICE_PRO', ''),
                'enterprise' => (string) env('STRIPE_REGIONAL_ANALYTICS_PRICE_ENTERPRISE', ''),
                default => (string) env('STRIPE_REGIONAL_ANALYTICS_PRICE_BASIC', ''),
            };

            if ($priceId === '') {
                Log::warning('RegionalAnalyticsBilling: missing Stripe price for tier ' . $planTier);
                return null;
            }

            $customer = \Stripe\Customer::create([
                'email' => $customerEmail,
                'metadata' => ['regional_analytics_subscription_id' => (string) $subscriptionId],
            ]);

            $sub = \Stripe\Subscription::create([
                'customer' => $customer->id,
                'items' => [['price' => $priceId]],
                'trial_period_days' => 14,
                'metadata' => ['regional_analytics_subscription_id' => (string) $subscriptionId],
            ]);

            return (string) $sub->id;
        } catch (\Throwable $e) {
            Log::error('RegionalAnalyticsBilling create failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Cancel an active Stripe subscription (best-effort). */
    public function cancelSubscription(string $stripeSubscriptionId): bool
    {
        if (! class_exists(\Stripe\Stripe::class) || $stripeSubscriptionId === '') {
            return false;
        }

        try {
            $secret = (string) config('services.stripe.secret', env('STRIPE_SECRET_KEY', ''));
            if ($secret === '') {
                return false;
            }
            \Stripe\Stripe::setApiKey($secret);
            \Stripe\Subscription::update($stripeSubscriptionId, ['cancel_at_period_end' => true]);
            return true;
        } catch (\Throwable $e) {
            Log::error('RegionalAnalyticsBilling cancel failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Webhook handler — invoice.paid.
     * Marks the subscription as `active` and records the new period.
     */
    public function handleInvoicePaid(array $event): void
    {
        $sub = $event['data']['object']['subscription'] ?? null;
        if (! $sub) {
            return;
        }
        DB::table('regional_analytics_subscriptions')
            ->where('stripe_subscription_id', $sub)
            ->update([
                'status' => 'active',
                'current_period_start' => isset($event['data']['object']['period_start'])
                    ? date('Y-m-d H:i:s', (int) $event['data']['object']['period_start'])
                    : null,
                'current_period_end' => isset($event['data']['object']['period_end'])
                    ? date('Y-m-d H:i:s', (int) $event['data']['object']['period_end'])
                    : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Webhook handler — customer.subscription.updated.
     */
    public function handleSubscriptionUpdated(array $event): void
    {
        $obj = $event['data']['object'] ?? [];
        $stripeId = $obj['id'] ?? null;
        if (! $stripeId) {
            return;
        }
        $statusMap = [
            'trialing' => 'trialing',
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'unpaid' => 'past_due',
            'incomplete' => 'past_due',
            'incomplete_expired' => 'cancelled',
        ];
        $status = $statusMap[$obj['status'] ?? ''] ?? 'past_due';

        DB::table('regional_analytics_subscriptions')
            ->where('stripe_subscription_id', $stripeId)
            ->update(['status' => $status, 'updated_at' => now()]);
    }
}
