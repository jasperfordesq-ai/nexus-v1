<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationAuditService;

/**
 * Federation Analytics Controller
 *
 * Provides analytics dashboard for federation activity:
 * - Cross-timebank activity metrics
 * - Partnership health metrics
 * - Popular federated content
 * - Activity trends and charts
 */
class FederationAnalyticsController
{
    public function __construct()
    {
        // Require admin role
        $role = $_SESSION['role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = in_array($role, ['super_admin', 'platform_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!isset($_SESSION['user_id']) || (!$isAdmin && !$isSuper && !$isAdminSession)) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    /**
     * Main analytics dashboard
     */
    public function index()
    {
        $tenantId = TenantContext::getId();

        // Check if federation is available for this tenant
        $systemEnabled = FederationFeatureService::isGloballyEnabled();
        $isWhitelisted = FederationFeatureService::isTenantWhitelisted($tenantId);

        if (!$systemEnabled || !$isWhitelisted) {
            View::render('admin/federation/analytics-unavailable', [
                'systemEnabled' => $systemEnabled,
                'isWhitelisted' => $isWhitelisted,
                'pageTitle' => 'Federation Analytics'
            ]);
            return;
        }

        // Get date range from query params (default: 30 days)
        $days = (int)($_GET['days'] ?? 30);
        $days = max(7, min(365, $days));

        // Get overall audit stats
        $auditStats = FederationAuditService::getStats($days);

        // Get partnership stats
        $partnershipStats = FederationPartnershipService::getStats();

        // Get tenant-specific analytics
        $tenantAnalytics = $this->getTenantAnalytics($tenantId, $days);

        // Get activity timeline data
        $activityTimeline = $this->getActivityTimeline($tenantId, $days);

        // Get partner activity breakdown
        $partnerActivity = $this->getPartnerActivity($tenantId, $days);

        // Get feature usage stats
        $featureUsage = $this->getFeatureUsage($tenantId, $days);

        // Get recent activity log for this tenant
        $recentActivity = $this->getRecentActivity($tenantId, 10);

        View::render('admin/federation/analytics', [
            'auditStats' => $auditStats,
            'partnershipStats' => $partnershipStats,
            'tenantAnalytics' => $tenantAnalytics,
            'activityTimeline' => $activityTimeline,
            'partnerActivity' => $partnerActivity,
            'featureUsage' => $featureUsage,
            'recentActivity' => $recentActivity,
            'days' => $days,
            'pageTitle' => 'Federation Analytics'
        ]);
    }

    /**
     * Get tenant-specific analytics
     */
    private function getTenantAnalytics(int $tenantId, int $days): array
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));

            // Messages sent/received
            $messageStats = Database::query("
                SELECT
                    COUNT(CASE WHEN sender_tenant_id = ? THEN 1 END) as sent,
                    COUNT(CASE WHEN receiver_tenant_id = ? THEN 1 END) as received
                FROM federation_messages
                WHERE (sender_tenant_id = ? OR receiver_tenant_id = ?)
                AND created_at >= ?
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $startDate])->fetch(\PDO::FETCH_ASSOC);

            // Transactions (if applicable)
            $transactionStats = Database::query("
                SELECT
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN sender_tenant_id = ? THEN amount ELSE 0 END) as hours_given,
                    SUM(CASE WHEN receiver_tenant_id = ? THEN amount ELSE 0 END) as hours_received
                FROM federation_transactions
                WHERE (sender_tenant_id = ? OR receiver_tenant_id = ?)
                AND created_at >= ?
                AND status = 'completed'
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $startDate])->fetch(\PDO::FETCH_ASSOC);

            // Profile views (federated)
            $profileViews = Database::query("
                SELECT COUNT(*) as views
                FROM federation_audit_log
                WHERE action_type = 'cross_tenant_profile_view'
                AND (source_tenant_id = ? OR target_tenant_id = ?)
                AND created_at >= ?
            ", [$tenantId, $tenantId, $startDate])->fetchColumn();

            // Listing interactions
            $listingStats = Database::query("
                SELECT COUNT(*) as interactions
                FROM federation_audit_log
                WHERE action_type IN ('listing_viewed', 'listing_favorited', 'listing_federated')
                AND (source_tenant_id = ? OR target_tenant_id = ?)
                AND created_at >= ?
            ", [$tenantId, $tenantId, $startDate])->fetchColumn();

            return [
                'messages_sent' => (int)($messageStats['sent'] ?? 0),
                'messages_received' => (int)($messageStats['received'] ?? 0),
                'total_transactions' => (int)($transactionStats['total_transactions'] ?? 0),
                'hours_given' => (float)($transactionStats['hours_given'] ?? 0),
                'hours_received' => (float)($transactionStats['hours_received'] ?? 0),
                'profile_views' => (int)$profileViews,
                'listing_interactions' => (int)$listingStats,
            ];

        } catch (\Exception $e) {
            error_log("FederationAnalyticsController::getTenantAnalytics error: " . $e->getMessage());
            return [
                'messages_sent' => 0,
                'messages_received' => 0,
                'total_transactions' => 0,
                'hours_given' => 0,
                'hours_received' => 0,
                'profile_views' => 0,
                'listing_interactions' => 0,
            ];
        }
    }

    /**
     * Get activity timeline for chart
     */
    private function getActivityTimeline(int $tenantId, int $days): array
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));

            $results = Database::query("
                SELECT
                    DATE(created_at) as date,
                    category,
                    COUNT(*) as count
                FROM federation_audit_log
                WHERE (source_tenant_id = ? OR target_tenant_id = ?)
                AND created_at >= ?
                GROUP BY DATE(created_at), category
                ORDER BY date ASC
            ", [$tenantId, $tenantId, $startDate])->fetchAll(\PDO::FETCH_ASSOC);

            // Organize by date
            $timeline = [];
            foreach ($results as $row) {
                $date = $row['date'];
                if (!isset($timeline[$date])) {
                    $timeline[$date] = [
                        'date' => $date,
                        'messaging' => 0,
                        'transaction' => 0,
                        'profile' => 0,
                        'listing' => 0,
                        'partnership' => 0,
                        'other' => 0,
                    ];
                }
                $category = $row['category'];
                if (isset($timeline[$date][$category])) {
                    $timeline[$date][$category] = (int)$row['count'];
                } else {
                    $timeline[$date]['other'] += (int)$row['count'];
                }
            }

            return array_values($timeline);

        } catch (\Exception $e) {
            error_log("FederationAnalyticsController::getActivityTimeline error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get partner-specific activity breakdown
     */
    private function getPartnerActivity(int $tenantId, int $days): array
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));

            // Get activity by partner
            $results = Database::query("
                SELECT
                    CASE
                        WHEN fal.source_tenant_id = ? THEN fal.target_tenant_id
                        ELSE fal.source_tenant_id
                    END as partner_id,
                    t.name as partner_name,
                    COUNT(*) as activity_count,
                    COUNT(CASE WHEN fal.category = 'messaging' THEN 1 END) as messages,
                    COUNT(CASE WHEN fal.category = 'transaction' THEN 1 END) as transactions,
                    COUNT(CASE WHEN fal.category = 'profile' THEN 1 END) as profile_views,
                    MAX(fal.created_at) as last_activity
                FROM federation_audit_log fal
                LEFT JOIN tenants t ON t.id = CASE
                    WHEN fal.source_tenant_id = ? THEN fal.target_tenant_id
                    ELSE fal.source_tenant_id
                END
                WHERE (fal.source_tenant_id = ? OR fal.target_tenant_id = ?)
                AND fal.target_tenant_id IS NOT NULL
                AND fal.created_at >= ?
                GROUP BY partner_id, t.name
                ORDER BY activity_count DESC
                LIMIT 10
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $startDate])->fetchAll(\PDO::FETCH_ASSOC);

            return $results;

        } catch (\Exception $e) {
            error_log("FederationAnalyticsController::getPartnerActivity error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get feature usage statistics
     */
    private function getFeatureUsage(int $tenantId, int $days): array
    {
        try {
            $startDate = date('Y-m-d', strtotime("-{$days} days"));

            $results = Database::query("
                SELECT
                    category,
                    COUNT(*) as usage_count
                FROM federation_audit_log
                WHERE (source_tenant_id = ? OR target_tenant_id = ?)
                AND created_at >= ?
                GROUP BY category
                ORDER BY usage_count DESC
            ", [$tenantId, $tenantId, $startDate])->fetchAll(\PDO::FETCH_ASSOC);

            return $results;

        } catch (\Exception $e) {
            error_log("FederationAnalyticsController::getFeatureUsage error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activity for this tenant
     */
    private function getRecentActivity(int $tenantId, int $limit = 10): array
    {
        try {
            $results = Database::query("
                SELECT
                    fal.id,
                    fal.action_type,
                    fal.category,
                    fal.level,
                    fal.source_tenant_id,
                    fal.target_tenant_id,
                    fal.actor_name,
                    fal.created_at,
                    CASE
                        WHEN fal.source_tenant_id = ? THEN t_target.name
                        ELSE t_source.name
                    END as related_tenant_name
                FROM federation_audit_log fal
                LEFT JOIN tenants t_source ON t_source.id = fal.source_tenant_id
                LEFT JOIN tenants t_target ON t_target.id = fal.target_tenant_id
                WHERE fal.source_tenant_id = ? OR fal.target_tenant_id = ?
                ORDER BY fal.created_at DESC
                LIMIT ?
            ", [$tenantId, $tenantId, $tenantId, $limit])->fetchAll(\PDO::FETCH_ASSOC);

            return $results;

        } catch (\Exception $e) {
            error_log("FederationAnalyticsController::getRecentActivity error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * API endpoint for analytics data (AJAX)
     */
    public function api()
    {
        header('Content-Type: application/json');

        $tenantId = TenantContext::getId();

        if (!FederationFeatureService::isTenantWhitelisted($tenantId)) {
            echo json_encode(['error' => 'Not authorized']);
            return;
        }

        $days = (int)($_GET['days'] ?? 30);
        $days = max(7, min(365, $days));

        $type = $_GET['type'] ?? 'overview';

        switch ($type) {
            case 'timeline':
                $data = $this->getActivityTimeline($tenantId, $days);
                break;
            case 'partners':
                $data = $this->getPartnerActivity($tenantId, $days);
                break;
            case 'features':
                $data = $this->getFeatureUsage($tenantId, $days);
                break;
            case 'overview':
            default:
                $data = [
                    'audit' => FederationAuditService::getStats($days),
                    'partnerships' => FederationPartnershipService::getStats(),
                    'tenant' => $this->getTenantAnalytics($tenantId, $days),
                ];
                break;
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
            'days' => $days,
        ]);
    }

    /**
     * Export analytics data (CSV)
     */
    public function export()
    {
        $tenantId = TenantContext::getId();

        if (!FederationFeatureService::isTenantWhitelisted($tenantId)) {
            http_response_code(403);
            echo "Not authorized";
            return;
        }

        $days = (int)($_GET['days'] ?? 30);
        $days = max(7, min(365, $days));

        $type = $_GET['type'] ?? 'activity';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="federation-analytics-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'partners':
                fputcsv($output, ['Partner Name', 'Total Activity', 'Messages', 'Transactions', 'Profile Views', 'Last Activity']);
                $data = $this->getPartnerActivity($tenantId, $days);
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['partner_name'] ?? 'Unknown',
                        $row['activity_count'],
                        $row['messages'],
                        $row['transactions'],
                        $row['profile_views'],
                        $row['last_activity'],
                    ]);
                }
                break;

            case 'activity':
            default:
                fputcsv($output, ['Date', 'Messaging', 'Transactions', 'Profile', 'Listings', 'Partnership', 'Other']);
                $data = $this->getActivityTimeline($tenantId, $days);
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['date'],
                        $row['messaging'],
                        $row['transaction'],
                        $row['profile'],
                        $row['listing'],
                        $row['partnership'],
                        $row['other'],
                    ]);
                }
                break;
        }

        fclose($output);
    }
}
