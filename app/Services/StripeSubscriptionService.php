<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StripeSubscriptionService — Manages Stripe product/price sync,
 * checkout sessions, billing portal, and webhook event processing
 * for tenant plan subscriptions.
 *
 * Static methods following project convention.
 */
class StripeSubscriptionService
{
    /**
     * Sync a pay_plan to Stripe (create/update Product + Prices).
     *
     * Idempotent: skips creation if Stripe IDs already exist.
     */
    public static function syncPlanToStripe(int $planId): void
    {
        $plan = DB::selectOne("SELECT * FROM pay_plans WHERE id = ?", [$planId]);

        if (!$plan) {
            Log::warning('StripeSubscriptionService::syncPlanToStripe — plan not found', ['plan_id' => $planId]);
            return;
        }

        try {
            $client = StripeService::client();
            $updates = [];
            $params = [];

            // --- Product ---
            if (empty($plan->stripe_product_id)) {
                $product = $client->products->create([
                    'name' => $plan->name,
                    'description' => $plan->description ?: "Plan: {$plan->name}",
                    'metadata' => ['nexus_plan_id' => (string) $planId],
                ]);
                $updates[] = 'stripe_product_id = ?';
                $params[] = $product->id;
                $stripeProductId = $product->id;

                Log::info('Stripe product created', ['plan_id' => $planId, 'product_id' => $product->id]);
            } else {
                $stripeProductId = $plan->stripe_product_id;

                // Update existing product name/description
                $client->products->update($stripeProductId, [
                    'name' => $plan->name,
                    'description' => $plan->description ?: "Plan: {$plan->name}",
                ]);
            }

            // --- Monthly Price ---
            if (empty($plan->stripe_price_id_monthly) && $plan->price_monthly > 0) {
                $monthlyPrice = $client->prices->create([
                    'product' => $stripeProductId,
                    'unit_amount' => (int) round($plan->price_monthly * 100),
                    'currency' => 'eur',
                    'recurring' => ['interval' => 'month'],
                    'metadata' => ['nexus_plan_id' => (string) $planId, 'interval' => 'monthly'],
                ]);
                $updates[] = 'stripe_price_id_monthly = ?';
                $params[] = $monthlyPrice->id;

                Log::info('Stripe monthly price created', ['plan_id' => $planId, 'price_id' => $monthlyPrice->id]);
            }

            // --- Yearly Price ---
            if (empty($plan->stripe_price_id_yearly) && $plan->price_yearly > 0) {
                $yearlyPrice = $client->prices->create([
                    'product' => $stripeProductId,
                    'unit_amount' => (int) round($plan->price_yearly * 100),
                    'currency' => 'eur',
                    'recurring' => ['interval' => 'year'],
                    'metadata' => ['nexus_plan_id' => (string) $planId, 'interval' => 'yearly'],
                ]);
                $updates[] = 'stripe_price_id_yearly = ?';
                $params[] = $yearlyPrice->id;

                Log::info('Stripe yearly price created', ['plan_id' => $planId, 'price_id' => $yearlyPrice->id]);
            }

            // Persist Stripe IDs back to pay_plans
            if (!empty($updates)) {
                $updates[] = 'updated_at = NOW()';
                $params[] = $planId;
                DB::update(
                    "UPDATE pay_plans SET " . implode(', ', $updates) . " WHERE id = ?",
                    $params
                );
            }
        } catch (\Exception $e) {
            Log::error('StripeSubscriptionService::syncPlanToStripe failed', [
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a Stripe Checkout Session for a tenant to subscribe to a plan.
     *
     * @return array{checkout_url: string, session_id: string}
     */
    public static function createCheckoutSession(int $tenantId, int $planId, string $billingInterval): array
    {
        $client = StripeService::client();

        // Load tenant
        $tenant = DB::selectOne("SELECT id, name, stripe_customer_id FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            throw new \RuntimeException("Tenant {$tenantId} not found");
        }

        // Get or create Stripe Customer
        $customerId = $tenant->stripe_customer_id;
        if (empty($customerId)) {
            try {
                $customer = $client->customers->create([
                    'name' => $tenant->name,
                    'metadata' => ['nexus_tenant_id' => (string) $tenantId],
                ]);
                $customerId = $customer->id;
                DB::update("UPDATE tenants SET stripe_customer_id = ? WHERE id = ?", [$customerId, $tenantId]);

                Log::info('Stripe customer created for tenant', ['tenant_id' => $tenantId, 'customer_id' => $customerId]);
            } catch (\Exception $e) {
                Log::error('Failed to create Stripe customer', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
                throw $e;
            }
        }

        // Load plan and resolve the correct price ID
        $plan = DB::selectOne("SELECT * FROM pay_plans WHERE id = ?", [$planId]);
        if (!$plan) {
            throw new \RuntimeException("Plan {$planId} not found");
        }

        $priceId = $billingInterval === 'yearly'
            ? $plan->stripe_price_id_yearly
            : $plan->stripe_price_id_monthly;

        // Auto-sync to Stripe if price IDs are missing (e.g., pre-existing plans)
        if (empty($priceId)) {
            Log::info("Auto-syncing plan {$planId} to Stripe (no price ID for {$billingInterval})");
            static::syncPlanToStripe($planId);
            $plan->refresh();
            $priceId = $billingInterval === 'yearly'
                ? $plan->stripe_price_id_yearly
                : $plan->stripe_price_id_monthly;

            if (empty($priceId)) {
                throw new \RuntimeException("Failed to sync plan {$planId} to Stripe — no price created for {$billingInterval}");
            }
        }

        // Build success/cancel URLs — point to the React admin billing page
        $frontendBase = rtrim(env('REACT_FRONTEND_URL', env('APP_URL', 'https://app.project-nexus.ie')), '/');
        $successUrl = $frontendBase . '/admin/billing/checkout-return?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $frontendBase . '/admin/billing?cancelled=1';

        try {
            $session = $client->checkout->sessions->create([
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => [
                    ['price' => $priceId, 'quantity' => 1],
                ],
                'metadata' => [
                    'nexus_tenant_id' => (string) $tenantId,
                    'nexus_plan_id' => (string) $planId,
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);

            Log::info('Stripe checkout session created', [
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'session_id' => $session->id,
            ]);

            return [
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe checkout session', [
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a Stripe Billing Portal Session for a tenant to manage their subscription.
     *
     * @return array{portal_url: string}
     */
    public static function createPortalSession(int $tenantId): array
    {
        $tenant = DB::selectOne("SELECT id, stripe_customer_id FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            throw new \RuntimeException("Tenant {$tenantId} not found");
        }

        if (empty($tenant->stripe_customer_id)) {
            throw new \RuntimeException("Tenant {$tenantId} has no Stripe customer — subscribe to a plan first");
        }

        $frontendBase = rtrim(env('REACT_FRONTEND_URL', env('APP_URL', 'https://app.project-nexus.ie')), '/');

        try {
            $client = StripeService::client();
            $session = $client->billingPortal->sessions->create([
                'customer' => $tenant->stripe_customer_id,
                'return_url' => $frontendBase . '/admin/billing',
            ]);

            return ['portal_url' => $session->url];
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe portal session', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle checkout.session.completed — activate the subscription in our DB.
     */
    public static function handleCheckoutCompleted(object $session): void
    {
        $tenantId = (int) ($session->metadata->nexus_tenant_id ?? 0);
        $planId = (int) ($session->metadata->nexus_plan_id ?? 0);
        $subscriptionId = $session->subscription ?? null;

        if (!$tenantId || !$planId) {
            Log::warning('Stripe checkout.session.completed missing nexus metadata', [
                'session_id' => $session->id ?? null,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($tenantId, $planId, $subscriptionId, $session) {
                // Upsert tenant_plan_assignments
                $existing = DB::selectOne(
                    "SELECT id FROM tenant_plan_assignments WHERE tenant_id = ?",
                    [$tenantId]
                );

                if ($existing) {
                    DB::update(
                        "UPDATE tenant_plan_assignments
                         SET pay_plan_id = ?, status = 'active', stripe_subscription_id = ?,
                             starts_at = NOW(), expires_at = NULL, updated_at = NOW()
                         WHERE tenant_id = ?",
                        [$planId, $subscriptionId, $tenantId]
                    );
                } else {
                    DB::insert(
                        "INSERT INTO tenant_plan_assignments
                         (tenant_id, pay_plan_id, status, stripe_subscription_id, starts_at, created_at, updated_at)
                         VALUES (?, ?, 'active', ?, NOW(), NOW(), NOW())",
                        [$tenantId, $planId, $subscriptionId]
                    );
                }
            });

            Log::info('Stripe checkout completed — subscription activated', [
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'subscription_id' => $subscriptionId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle checkout.session.completed', [
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle customer.subscription.updated — sync status and period end.
     */
    public static function handleSubscriptionUpdated(object $subscription): void
    {
        $stripeSubId = $subscription->id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $assignment = DB::selectOne(
            "SELECT id, tenant_id FROM tenant_plan_assignments WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );

        if (!$assignment) {
            Log::info('Stripe subscription.updated — no matching assignment', ['subscription_id' => $stripeSubId]);
            return;
        }

        // Map Stripe status to our status
        $stripeStatus = $subscription->status ?? 'active';
        $statusMap = [
            'active' => 'active',
            'past_due' => 'active',
            'canceled' => 'cancelled',
            'trialing' => 'trial',
            'unpaid' => 'expired',
            'incomplete' => 'active',
            'incomplete_expired' => 'expired',
            'paused' => 'expired',
        ];
        $nexusStatus = $statusMap[$stripeStatus] ?? 'active';

        $periodEnd = isset($subscription->current_period_end)
            ? date('Y-m-d H:i:s', $subscription->current_period_end)
            : null;

        try {
            DB::update(
                "UPDATE tenant_plan_assignments
                 SET status = ?, stripe_current_period_end = ?, updated_at = NOW()
                 WHERE id = ?",
                [$nexusStatus, $periodEnd, $assignment->id]
            );

            Log::info('Stripe subscription updated', [
                'tenant_id' => $assignment->tenant_id,
                'subscription_id' => $stripeSubId,
                'status' => $nexusStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle subscription.updated', [
                'subscription_id' => $stripeSubId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle customer.subscription.deleted — mark as cancelled.
     */
    public static function handleSubscriptionDeleted(object $subscription): void
    {
        $stripeSubId = $subscription->id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $assignment = DB::selectOne(
            "SELECT id, tenant_id FROM tenant_plan_assignments WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );

        if (!$assignment) {
            Log::info('Stripe subscription.deleted — no matching assignment', ['subscription_id' => $stripeSubId]);
            return;
        }

        try {
            DB::update(
                "UPDATE tenant_plan_assignments
                 SET status = 'cancelled', expires_at = NOW(), updated_at = NOW()
                 WHERE id = ?",
                [$assignment->id]
            );

            Log::info('Stripe subscription deleted — marked cancelled', [
                'tenant_id' => $assignment->tenant_id,
                'subscription_id' => $stripeSubId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle subscription.deleted', [
                'subscription_id' => $stripeSubId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle invoice.paid — update current_period_end.
     */
    public static function handleInvoicePaid(object $invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;
        if (!$stripeSubId) {
            return;
        }

        $assignment = DB::selectOne(
            "SELECT id, tenant_id FROM tenant_plan_assignments WHERE stripe_subscription_id = ?",
            [$stripeSubId]
        );

        if (!$assignment) {
            return;
        }

        // Fetch the subscription to get updated period_end
        try {
            $client = StripeService::client();
            $sub = $client->subscriptions->retrieve($stripeSubId);
            $periodEnd = isset($sub->current_period_end)
                ? date('Y-m-d H:i:s', $sub->current_period_end)
                : null;

            DB::update(
                "UPDATE tenant_plan_assignments
                 SET stripe_current_period_end = ?, updated_at = NOW()
                 WHERE id = ?",
                [$periodEnd, $assignment->id]
            );

            Log::info('Stripe invoice paid — period end updated', [
                'tenant_id' => $assignment->tenant_id,
                'subscription_id' => $stripeSubId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle invoice.paid', [
                'subscription_id' => $stripeSubId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle invoice.payment_failed — log warning.
     */
    public static function handleInvoicePaymentFailed(object $invoice): void
    {
        $stripeSubId = $invoice->subscription ?? null;

        $assignment = null;
        if ($stripeSubId) {
            $assignment = DB::selectOne(
                "SELECT id, tenant_id FROM tenant_plan_assignments WHERE stripe_subscription_id = ?",
                [$stripeSubId]
            );
        }

        Log::warning('Stripe invoice payment failed', [
            'invoice_id' => $invoice->id ?? null,
            'subscription_id' => $stripeSubId,
            'tenant_id' => $assignment->tenant_id ?? null,
            'customer_id' => $invoice->customer ?? null,
            'amount_due' => $invoice->amount_due ?? null,
        ]);
    }

    /**
     * Get subscription details for a tenant.
     *
     * @return array|null Subscription info or null if no assignment exists
     */
    public static function getSubscriptionDetails(int $tenantId): ?array
    {
        $result = DB::selectOne(
            "SELECT tpa.id, tpa.tenant_id, tpa.pay_plan_id, tpa.status,
                    tpa.starts_at, tpa.expires_at, tpa.trial_ends_at,
                    tpa.stripe_subscription_id, tpa.stripe_current_period_end,
                    tpa.created_at, tpa.updated_at,
                    pp.name AS plan_name, pp.slug AS plan_slug,
                    pp.tier_level AS plan_tier_level, pp.description AS plan_description,
                    pp.price_monthly, pp.price_yearly
             FROM tenant_plan_assignments tpa
             JOIN pay_plans pp ON pp.id = tpa.pay_plan_id
             WHERE tpa.tenant_id = ?
             ORDER BY tpa.created_at DESC
             LIMIT 1",
            [$tenantId]
        );

        if (!$result) {
            return null;
        }

        return (array) $result;
    }

    /**
     * Get invoice history for a tenant from Stripe.
     *
     * @return array List of invoice summaries
     */
    public static function getInvoiceHistory(int $tenantId): array
    {
        $tenant = DB::selectOne("SELECT stripe_customer_id FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant || empty($tenant->stripe_customer_id)) {
            return [];
        }

        try {
            $client = StripeService::client();
            $invoices = $client->invoices->all([
                'customer' => $tenant->stripe_customer_id,
                'limit' => 50,
            ]);

            $result = [];
            foreach ($invoices->data as $invoice) {
                $result[] = [
                    'id' => $invoice->id,
                    'amount_paid' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'created' => date('Y-m-d H:i:s', $invoice->created),
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Stripe invoice history', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
