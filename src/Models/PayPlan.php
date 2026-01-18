<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class PayPlan
{
    /**
     * Get all active pay plans
     */
    public static function all($activeOnly = true)
    {
        $db = Database::getConnection();

        if ($activeOnly) {
            $stmt = $db->query("SELECT * FROM pay_plans WHERE is_active = 1 ORDER BY tier_level ASC");
        } else {
            $stmt = $db->query("SELECT * FROM pay_plans ORDER BY tier_level ASC");
        }

        return $stmt->fetchAll();
    }

    /**
     * Find a pay plan by ID
     */
    public static function find($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pay_plans WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find a pay plan by slug
     */
    public static function findBySlug($slug)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pay_plans WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * Get current active plan for a tenant
     */
    public static function getCurrentPlanForTenant($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "SELECT pp.*, tpa.status, tpa.starts_at, tpa.expires_at, tpa.trial_ends_at
            FROM pay_plans pp
            INNER JOIN tenant_plan_assignments tpa ON pp.id = tpa.pay_plan_id
            WHERE tpa.tenant_id = ?
            AND tpa.status = 'active'
            AND (tpa.expires_at IS NULL OR tpa.expires_at > NOW())
            ORDER BY pp.tier_level DESC
            LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }

    /**
     * Get tenant's plan assignment (includes expired/cancelled)
     */
    public static function getTenantAssignment($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "SELECT pp.*, tpa.status, tpa.starts_at, tpa.expires_at, tpa.trial_ends_at
            FROM pay_plans pp
            INNER JOIN tenant_plan_assignments tpa ON pp.id = tpa.pay_plan_id
            WHERE tpa.tenant_id = ?
            ORDER BY tpa.id DESC
            LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }

    /**
     * Assign a plan to a tenant
     */
    public static function assignToTenant($tenantId, $planId, $expiresAt = null, $status = 'active')
    {
        $db = Database::getConnection();

        // First, expire any existing active assignments
        $stmt = $db->prepare(
            "UPDATE tenant_plan_assignments
            SET status = 'expired', updated_at = NOW()
            WHERE tenant_id = ? AND status = 'active'"
        );
        $stmt->execute([$tenantId]);

        // Create new assignment
        $stmt = $db->prepare(
            "INSERT INTO tenant_plan_assignments
            (tenant_id, pay_plan_id, status, starts_at, expires_at, created_at)
            VALUES (?, ?, ?, NOW(), ?, NOW())"
        );
        $stmt->execute([$tenantId, $planId, $status, $expiresAt]);

        return Database::lastInsertId();
    }

    /**
     * Check if tenant has access to a specific feature
     */
    public static function hasFeature($feature, $tenantId = null)
    {
        $plan = self::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return false;
        }

        $features = is_string($plan['features'])
            ? json_decode($plan['features'], true)
            : $plan['features'];

        if (!is_array($features)) {
            return false;
        }

        return isset($features[$feature]) && $features[$feature] === true;
    }

    /**
     * Check if tenant can access a specific layout
     */
    public static function canAccessLayout($layout, $tenantId = null)
    {
        $plan = self::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return false;
        }

        $allowedLayouts = is_string($plan['allowed_layouts'])
            ? json_decode($plan['allowed_layouts'], true)
            : $plan['allowed_layouts'];

        if (!is_array($allowedLayouts)) {
            return false;
        }

        return in_array($layout, $allowedLayouts);
    }

    /**
     * Get tenant's tier level
     */
    public static function getTierLevel($tenantId = null)
    {
        $plan = self::getCurrentPlanForTenant($tenantId);
        return $plan ? (int)$plan['tier_level'] : 0;
    }

    /**
     * Get all features for tenant's plan
     */
    public static function getTenantFeatures($tenantId = null)
    {
        $plan = self::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return [];
        }

        $features = is_string($plan['features'])
            ? json_decode($plan['features'], true)
            : $plan['features'];

        return is_array($features) ? $features : [];
    }

    /**
     * Get allowed layouts for tenant
     */
    public static function getAllowedLayouts($tenantId = null)
    {
        $plan = self::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return ['modern']; // Default fallback
        }

        $allowedLayouts = is_string($plan['allowed_layouts'])
            ? json_decode($plan['allowed_layouts'], true)
            : $plan['allowed_layouts'];

        return is_array($allowedLayouts) ? $allowedLayouts : ['modern'];
    }

    /**
     * Get plan limits
     */
    public static function getPlanLimits($tenantId = null)
    {
        $plan = self::getCurrentPlanForTenant($tenantId);

        if (!$plan) {
            return [
                'max_menus' => 1,
                'max_menu_items' => 10
            ];
        }

        return [
            'max_menus' => (int)($plan['max_menus'] ?? 1),
            'max_menu_items' => (int)($plan['max_menu_items'] ?? 10)
        ];
    }

    /**
     * Check if plan is in trial period
     */
    public static function isInTrial($tenantId = null)
    {
        $assignment = self::getTenantAssignment($tenantId);

        if (!$assignment || $assignment['status'] !== 'trial') {
            return false;
        }

        if (!$assignment['trial_ends_at']) {
            return false;
        }

        return strtotime($assignment['trial_ends_at']) > time();
    }

    /**
     * Check if plan has expired
     */
    public static function hasExpired($tenantId = null)
    {
        $assignment = self::getTenantAssignment($tenantId);

        if (!$assignment) {
            return true;
        }

        if ($assignment['status'] === 'expired' || $assignment['status'] === 'cancelled') {
            return true;
        }

        if ($assignment['expires_at'] && strtotime($assignment['expires_at']) < time()) {
            return true;
        }

        return false;
    }

    /**
     * Get plan comparison data (for pricing pages)
     */
    public static function getComparison()
    {
        $plans = self::all(true);
        $comparison = [];

        foreach ($plans as $plan) {
            $features = is_string($plan['features'])
                ? json_decode($plan['features'], true)
                : $plan['features'];

            $allowedLayouts = is_string($plan['allowed_layouts'])
                ? json_decode($plan['allowed_layouts'], true)
                : $plan['allowed_layouts'];

            $comparison[] = [
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

        return $comparison;
    }
}
