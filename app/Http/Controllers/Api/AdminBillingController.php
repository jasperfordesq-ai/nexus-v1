<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\StripeSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminBillingController — Tenant billing: subscription status,
 * Stripe Checkout, Billing Portal, and invoice history.
 */
class AdminBillingController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /v2/admin/billing/subscription
     *
     * Returns the current tenant's subscription details.
     */
    public function getSubscription(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $subscription = StripeSubscriptionService::getSubscriptionDetails($tenantId);

        return $this->respondWithData($subscription);
    }

    /**
     * POST /v2/admin/billing/checkout
     *
     * Creates a Stripe Checkout Session for the tenant.
     * Accepts: { plan_id: int, billing_interval: 'monthly'|'yearly' }
     */
    public function createCheckoutSession(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $planId = (int) $this->requireInput('plan_id');
        $billingInterval = $this->requireInput('billing_interval');

        if (!in_array($billingInterval, ['monthly', 'yearly'], true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'billing_interval must be "monthly" or "yearly"',
                'billing_interval',
                422
            );
        }

        if ($planId < 1) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid plan_id', 'plan_id', 422);
        }

        try {
            $result = StripeSubscriptionService::createCheckoutSession($tenantId, $planId, $billingInterval);
            return $this->respondWithData($result);
        } catch (\Exception $e) {
            Log::error('AdminBillingController::createCheckoutSession failed', [
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'STRIPE_ERROR',
                'Failed to create checkout session. Please try again.',
                null,
                500
            );
        }
    }

    /**
     * POST /v2/admin/billing/portal
     *
     * Creates a Stripe Billing Portal Session for the tenant.
     */
    public function createPortalSession(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Check if tenant has a Stripe customer before attempting portal
        $tenant = DB::selectOne("SELECT stripe_customer_id FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant || empty($tenant->stripe_customer_id)) {
            return $this->respondWithError(
                'NO_SUBSCRIPTION',
                'No active subscription. Subscribe to a plan first to manage payment methods.',
                null,
                400
            );
        }

        try {
            $result = StripeSubscriptionService::createPortalSession($tenantId);
            return $this->respondWithData($result);
        } catch (\Exception $e) {
            Log::error('AdminBillingController::createPortalSession failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'STRIPE_ERROR',
                'Failed to create billing portal session. Please try again.',
                null,
                500
            );
        }
    }

    /**
     * GET /v2/admin/billing/invoices
     *
     * Returns the tenant's Stripe invoice history.
     */
    public function getInvoices(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $invoices = StripeSubscriptionService::getInvoiceHistory($tenantId);

        return $this->respondWithData($invoices);
    }

    /**
     * GET /v2/billing/plans (public, no auth)
     *
     * Returns all active plans with prices for the pricing page.
     */
    public function getPlansPublic(): JsonResponse
    {
        $plans = array_map(fn($r) => (array) $r, DB::select(
            "SELECT id, name, slug, description, tier_level, features,
                    price_monthly, price_yearly, is_active
             FROM pay_plans
             WHERE is_active = 1
             ORDER BY tier_level ASC, name ASC"
        ));

        foreach ($plans as &$plan) {
            if (isset($plan['features'])) {
                $decoded = json_decode($plan['features'], true) ?: [];
                // Features may be stored as {"listings": true, "groups": true} (object)
                // or as ["listings", "groups"] (array). Normalize to string[] for frontend.
                if (is_array($decoded) && !array_is_list($decoded)) {
                    // Object format — extract keys where value is truthy
                    $plan['features'] = array_keys(array_filter($decoded));
                } else {
                    $plan['features'] = array_values($decoded);
                }
            }
        }
        unset($plan);

        return $this->respondWithData($plans);
    }
}
