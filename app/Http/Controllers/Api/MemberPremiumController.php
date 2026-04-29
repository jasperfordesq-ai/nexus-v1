<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\MemberPremiumService;
use Illuminate\Http\JsonResponse;

/**
 * AG58 — Member Premium Tier paywall (member-facing endpoints).
 */
class MemberPremiumController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/member-premium/tiers — public list of active tiers.
     */
    public function listTiers(): JsonResponse
    {
        $guard = $this->guardFeature();
        if ($guard !== null) {
            return $guard;
        }

        $tiers = MemberPremiumService::listTiers(TenantContext::getId());

        // Strip Stripe price IDs from member view (not needed client-side).
        $tiers = array_map(function ($t) {
            unset($t['stripe_price_id_monthly'], $t['stripe_price_id_yearly']);
            return $t;
        }, $tiers);

        return $this->respondWithData(['tiers' => $tiers]);
    }

    /**
     * GET /api/v2/member-premium/me — current user's subscription info.
     */
    public function me(): JsonResponse
    {
        $userId = $this->requireAuth();
        $guard = $this->guardFeature();
        if ($guard !== null) {
            return $guard;
        }

        $sub = MemberPremiumService::getMemberSubscription($userId);
        $tier = MemberPremiumService::getMemberTier($userId);

        return $this->respondWithData([
            'subscription' => $sub,
            'entitled_tier' => $tier,
            'unlocked_features' => $tier['features'] ?? [],
        ]);
    }

    /**
     * POST /api/v2/member-premium/checkout — create Stripe Checkout session.
     */
    public function checkout(): JsonResponse
    {
        $userId = $this->requireAuth();
        $guard = $this->guardFeature();
        if ($guard !== null) {
            return $guard;
        }

        $tierId = $this->inputInt('tier_id');
        $interval = (string) ($this->input('interval') ?? 'monthly');
        $returnUrl = (string) ($this->input('return_url') ?? '');

        if (! $tierId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'tier_id']), 'tier_id', 422);
        }
        if (! in_array($interval, ['monthly', 'yearly'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid interval (must be monthly or yearly)', 'interval', 422);
        }
        if ($returnUrl === '') {
            // Sensible default: tenant frontend /premium/return
            $returnUrl = rtrim(TenantContext::getFrontendUrl(), '/')
                . TenantContext::getSlugPrefix()
                . '/premium/return';
        }

        try {
            $result = MemberPremiumService::createCheckoutSession($userId, $tierId, $interval, $returnUrl);
            return $this->respondWithData($result);
        } catch (\Throwable $e) {
            return $this->respondWithError('CHECKOUT_FAILED', $e->getMessage(), null, 400);
        }
    }

    /**
     * POST /api/v2/member-premium/cancel — cancel current subscription at period end.
     */
    public function cancel(): JsonResponse
    {
        $userId = $this->requireAuth();
        $guard = $this->guardFeature();
        if ($guard !== null) {
            return $guard;
        }

        try {
            $ok = MemberPremiumService::cancel($userId, true);
            if (! $ok) {
                return $this->respondWithError('NO_SUBSCRIPTION', 'No active subscription to cancel', null, 404);
            }
            return $this->respondWithData(['cancelled' => true]);
        } catch (\Throwable $e) {
            return $this->respondWithError('CANCEL_FAILED', $e->getMessage(), null, 400);
        }
    }

    /**
     * POST /api/v2/member-premium/billing-portal — Stripe billing portal URL.
     */
    public function billingPortal(): JsonResponse
    {
        $userId = $this->requireAuth();
        $guard = $this->guardFeature();
        if ($guard !== null) {
            return $guard;
        }

        $returnUrl = (string) ($this->input('return_url') ?? '');
        if ($returnUrl === '') {
            $returnUrl = rtrim(TenantContext::getFrontendUrl(), '/')
                . TenantContext::getSlugPrefix()
                . '/premium/manage';
        }

        try {
            $result = MemberPremiumService::createBillingPortalSession($userId, $returnUrl);
            return $this->respondWithData($result);
        } catch (\Throwable $e) {
            return $this->respondWithError('PORTAL_FAILED', $e->getMessage(), null, 400);
        }
    }

    private function guardFeature(): ?JsonResponse
    {
        if (! TenantContext::hasFeature('member_premium')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        return null;
    }
}
