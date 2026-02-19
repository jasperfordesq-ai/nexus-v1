<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Models\PayPlan;
use Nexus\Models\Menu;
use Nexus\Core\TenantContext;

/**
 * PayPlanService - Business logic for pay plan management
 *
 * Handles plan validation, feature access, layout restrictions,
 * and plan upgrade/downgrade logic.
 */
class PayPlanService
{
    /**
     * Validate if tenant can access a layout
     * Returns true/false and optional error message
     */
    public static function validateLayoutAccess($layout, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Super admins can access everything
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
            return ['allowed' => true];
        }

        // Get tenant's current plan
        $plan = PayPlan::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return [
                'allowed' => false,
                'reason' => 'No active plan found. Please subscribe to a plan.',
                'upgrade_required' => true
            ];
        }

        // Check if plan has expired
        if (PayPlan::hasExpired($tenantId)) {
            return [
                'allowed' => false,
                'reason' => 'Your plan has expired. Please renew to continue.',
                'upgrade_required' => true
            ];
        }

        // Check if layout is allowed
        if (!PayPlan::canAccessLayout($layout, $tenantId)) {
            $allowedLayouts = PayPlan::getAllowedLayouts($tenantId);
            return [
                'allowed' => false,
                'reason' => "The '{$layout}' layout requires a higher tier plan.",
                'current_plan' => $plan['name'],
                'allowed_layouts' => $allowedLayouts,
                'upgrade_required' => true
            ];
        }

        return ['allowed' => true, 'plan' => $plan];
    }

    /**
     * Validate if tenant can use a feature
     */
    public static function validateFeatureAccess($feature, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Super admins can access everything
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']) {
            return ['allowed' => true];
        }

        $plan = PayPlan::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return [
                'allowed' => false,
                'reason' => 'No active plan found.',
                'upgrade_required' => true
            ];
        }

        if (!PayPlan::hasFeature($feature, $tenantId)) {
            return [
                'allowed' => false,
                'reason' => "The '{$feature}' feature requires a higher tier plan.",
                'current_plan' => $plan['name'],
                'upgrade_required' => true
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check if tenant can create more menus
     */
    public static function validateMenuCreation($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $limits = PayPlan::getPlanLimits($tenantId);
        $currentCount = Menu::count($tenantId);

        if ($currentCount >= $limits['max_menus']) {
            $plan = PayPlan::getCurrentPlanForTenant($tenantId);
            return [
                'allowed' => false,
                'reason' => "You have reached the maximum number of menus ({$limits['max_menus']}) for your plan.",
                'current_count' => $currentCount,
                'max_allowed' => $limits['max_menus'],
                'current_plan' => $plan['name'] ?? 'Unknown',
                'upgrade_required' => true
            ];
        }

        return [
            'allowed' => true,
            'current_count' => $currentCount,
            'max_allowed' => $limits['max_menus'],
            'remaining' => $limits['max_menus'] - $currentCount
        ];
    }

    /**
     * Get upgrade suggestions based on needs
     */
    public static function getUpgradeSuggestions($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $currentPlan = PayPlan::getCurrentPlanForTenant($tenantId);
        $currentTier = $currentPlan ? (int)$currentPlan['tier_level'] : 0;

        $allPlans = PayPlan::all(true);
        $suggestions = [];

        foreach ($allPlans as $plan) {
            if ((int)$plan['tier_level'] > $currentTier) {
                $features = is_string($plan['features'])
                    ? json_decode($plan['features'], true)
                    : $plan['features'];

                $allowedLayouts = is_string($plan['allowed_layouts'])
                    ? json_decode($plan['allowed_layouts'], true)
                    : $plan['allowed_layouts'];

                $suggestions[] = [
                    'id' => $plan['id'],
                    'name' => $plan['name'],
                    'slug' => $plan['slug'],
                    'description' => $plan['description'],
                    'tier_level' => $plan['tier_level'],
                    'price_monthly' => $plan['price_monthly'],
                    'price_yearly' => $plan['price_yearly'],
                    'features' => $features,
                    'allowed_layouts' => $allowedLayouts,
                    'max_menus' => $plan['max_menus'],
                    'max_menu_items' => $plan['max_menu_items']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Assign a plan to tenant (upgrade/downgrade/new subscription)
     */
    public static function assignPlan($tenantId, $planId, $expiresAt = null, $isTrial = false)
    {
        $plan = PayPlan::find($planId);

        if (!$plan) {
            return [
                'success' => false,
                'error' => 'Invalid plan selected.'
            ];
        }

        $status = $isTrial ? 'trial' : 'active';

        try {
            $assignmentId = PayPlan::assignToTenant($tenantId, $planId, $expiresAt, $status);

            // Clear any cached menu data for this tenant
            self::clearTenantCache($tenantId);

            return [
                'success' => true,
                'assignment_id' => $assignmentId,
                'plan' => $plan,
                'message' => "Successfully subscribed to {$plan['name']} plan."
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to assign plan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Start a trial for a tenant
     */
    public static function startTrial($tenantId, $planId, $trialDays = 14)
    {
        $trialEnds = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));

        return self::assignPlan($tenantId, $planId, $trialEnds, true);
    }

    /**
     * Clear cached data for a tenant (menus, etc.)
     */
    private static function clearTenantCache($tenantId)
    {
        $db = \Nexus\Core\Database::getConnection();
        $stmt = $db->prepare("DELETE FROM menu_cache WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
    }

    /**
     * Get plan status summary for a tenant
     */
    public static function getPlanStatus($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $plan = PayPlan::getCurrentPlanForTenant($tenantId);
        $assignment = PayPlan::getTenantAssignment($tenantId);

        if (!$plan || !$assignment) {
            return [
                'has_plan' => false,
                'status' => 'none',
                'message' => 'No active plan'
            ];
        }

        $status = [
            'has_plan' => true,
            'plan_name' => $plan['name'],
            'plan_slug' => $plan['slug'],
            'tier_level' => $plan['tier_level'],
            'status' => $assignment['status'],
            'starts_at' => $assignment['starts_at'],
            'expires_at' => $assignment['expires_at'],
            'is_trial' => PayPlan::isInTrial($tenantId),
            'has_expired' => PayPlan::hasExpired($tenantId),
            'features' => PayPlan::getTenantFeatures($tenantId),
            'allowed_layouts' => PayPlan::getAllowedLayouts($tenantId),
            'limits' => PayPlan::getPlanLimits($tenantId)
        ];

        // Add warning messages
        if ($status['is_trial']) {
            $daysLeft = ceil((strtotime($assignment['trial_ends_at']) - time()) / 86400);
            $status['message'] = "Trial expires in {$daysLeft} days";
            $status['warning'] = true;
        } elseif ($status['has_expired']) {
            $status['message'] = 'Plan has expired';
            $status['error'] = true;
        } elseif ($assignment['expires_at']) {
            $daysLeft = ceil((strtotime($assignment['expires_at']) - time()) / 86400);
            if ($daysLeft <= 7) {
                $status['message'] = "Plan expires in {$daysLeft} days";
                $status['warning'] = true;
            }
        }

        return $status;
    }

    /**
     * Check if tenant can downgrade to a specific plan
     */
    public static function canDowngradeTo($planId, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $targetPlan = PayPlan::find($planId);
        if (!$targetPlan) {
            return [
                'allowed' => false,
                'reason' => 'Target plan not found.'
            ];
        }

        // Check if current usage exceeds new plan limits
        $currentMenuCount = Menu::count($tenantId);
        $newMaxMenus = $targetPlan['max_menus'];

        if ($currentMenuCount > $newMaxMenus) {
            return [
                'allowed' => false,
                'reason' => "You have {$currentMenuCount} menus, but the {$targetPlan['name']} plan only allows {$newMaxMenus}.",
                'requires_action' => 'delete_menus',
                'excess_count' => $currentMenuCount - $newMaxMenus
            ];
        }

        return ['allowed' => true];
    }
}
