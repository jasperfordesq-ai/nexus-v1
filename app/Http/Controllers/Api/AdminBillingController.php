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
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_plan_id'), 'plan_id', 422);
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

        // Deduplicate by tier_level — keep lowest id per tier to avoid showing multiple free/same-tier plans
        $seenTiers = [];
        $plans = array_values(array_filter($plans, function (array $plan) use (&$seenTiers): bool {
            $tier = $plan['tier_level'];
            if (in_array($tier, $seenTiers, true)) {
                return false;
            }
            $seenTiers[] = $tier;
            return true;
        }));

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

    /**
     * POST /v2/admin/billing/upgrade-request
     *
     * Tenant admin submits an upgrade request to God.
     * Logs to billing_audit_log and sends email to Jasper.
     * Body: { message?: string }
     */
    public function requestUpgrade(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $user     = $this->getAuthUser();

        $message  = $this->getInput('message', '');

        // Fetch tenant name
        $tenant = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Unknown';

        // Log to billing_audit_log
        DB::table('billing_audit_log')->insert([
            'tenant_id'       => $tenantId,
            'acted_by_user_id' => $user->id ?? null,
            'action'          => 'upgrade_requested',
            'old_value'       => null,
            'new_value'       => json_encode(['message' => $message]),
            'notes'           => 'Self-service upgrade request from tenant admin',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Email Jasper
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Upgrade request from tenant: {$tenant} (ID: {$tenantId})\n\n" .
                "Requested by: " . ($user->email ?? 'unknown') . "\n\n" .
                "Message: " . ($message ?: '(none)'),
                function ($m) use ($tenant) {
                    $m->to('jasper@hour-timebank.ie')
                      ->subject("Plan Upgrade Request — {$tenant}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('BillingController: upgrade request email failed', ['error' => $e->getMessage()]);
        }

        return $this->respondWithData(['sent' => true]);
    }
}
