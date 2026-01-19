<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Service to fetch badge counts for admin sidebar navigation
 * Provides counts for pending approvals, alerts, and other actionable items
 */
class AdminBadgeCountService
{
    private static ?array $cachedCounts = null;

    /**
     * Get all badge counts for the current tenant
     * Results are cached for the request lifetime
     */
    public static function getCounts(): array
    {
        if (self::$cachedCounts !== null) {
            return self::$cachedCounts;
        }

        $tenantId = TenantContext::getId();
        $counts = [];

        try {
            // Pending user approvals
            $counts['pending_users'] = self::countPendingUsers($tenantId);

            // Pending listing reviews
            $counts['pending_listings'] = self::countPendingListings($tenantId);

            // Pending organization approvals
            $counts['pending_orgs'] = self::countPendingOrganizations($tenantId);

            // Fraud alerts (unreviewed)
            $counts['fraud_alerts'] = self::countFraudAlerts($tenantId);

            // GDPR data requests (pending)
            $counts['gdpr_requests'] = self::countGdprRequests($tenantId);

            // 404 errors (recent, unresolved)
            $counts['404_errors'] = self::count404Errors($tenantId);

        } catch (\Exception $e) {
            error_log("AdminBadgeCountService error: " . $e->getMessage());
            // Return empty counts on error
        }

        self::$cachedCounts = $counts;
        return $counts;
    }

    /**
     * Get a specific badge count
     */
    public static function getCount(string $key): int
    {
        $counts = self::getCounts();
        return $counts[$key] ?? 0;
    }

    /**
     * Count pending user approvals
     */
    private static function countPendingUsers(int $tenantId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM users
                 WHERE tenant_id = ? AND (status = 'pending' OR approval_status = 'pending')",
                [$tenantId]
            )->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count pending listing reviews
     */
    private static function countPendingListings(int $tenantId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM listings
                 WHERE tenant_id = ? AND (status = 'pending' OR status IS NULL OR status = '')",
                [$tenantId]
            )->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count pending organization approvals
     */
    private static function countPendingOrganizations(int $tenantId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM vol_organizations
                 WHERE tenant_id = ? AND status = 'pending'",
                [$tenantId]
            )->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count unreviewed fraud alerts
     */
    private static function countFraudAlerts(int $tenantId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM fraud_alerts
                 WHERE tenant_id = ? AND status = 'new'",
                [$tenantId]
            )->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count pending GDPR data requests
     */
    private static function countGdprRequests(int $tenantId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM gdpr_requests
                 WHERE tenant_id = ? AND status = 'pending'",
                [$tenantId]
            )->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Count recent 404 errors (last 7 days, not resolved)
     */
    private static function count404Errors(int $tenantId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM error_404_log
                 WHERE tenant_id = ? AND resolved = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId]
            )->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clear cached counts (useful after an action that changes counts)
     */
    public static function clearCache(): void
    {
        self::$cachedCounts = null;
    }
}
