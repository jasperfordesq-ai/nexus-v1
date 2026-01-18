<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;

/**
 * Federation Admin Controller
 *
 * Admin dashboard for federation activity and partnership management
 */
class FederationAdminController
{
    /**
     * Check admin access
     */
    private function checkAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // GOD MODE: Bypass all permission checks
        if (!empty($_SESSION['is_god'])) {
            return;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            $this->forbidden();
        }

        // Verify tenant match for non-super admins
        if (!$isSuper) {
            $currentUser = Database::query("SELECT tenant_id FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch();
            if ((int)$currentUser['tenant_id'] !== (int)TenantContext::getId()) {
                $this->forbidden();
            }
        }
    }

    private function forbidden()
    {
        header('HTTP/1.0 403 Forbidden');
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this area.</p>";
        echo "<a href='" . TenantContext::getBasePath() . "/dashboard'>Go Home</a>";
        exit;
    }

    /**
     * Display admin federation dashboard
     */
    public function index()
    {
        $this->checkAdmin();

        $tenantId = TenantContext::getId();
        $basePath = TenantContext::getBasePath();

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        // Get tenant's federation settings
        $tenantSettings = Database::query(
            "SELECT * FROM federation_tenant_settings WHERE tenant_id = ?",
            [$tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Get partnerships
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $activePartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'active');
        $pendingPartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'pending');

        // Get statistics
        $stats = $this->getStats($tenantId);

        // Get recent audit logs
        $auditLogs = $this->getRecentAuditLogs($tenantId);

        // Get opted-in users count
        $optedInUsers = $this->getOptedInUsersCount($tenantId);

        // Get activity trends (last 30 days)
        $activityTrends = $this->getActivityTrends($tenantId);

        // Get top active users
        $topUsers = $this->getTopActiveUsers($tenantId);

        \Nexus\Core\SEO::setTitle('Federation Admin Dashboard');

        View::render('admin/federation/dashboard', [
            'pageTitle' => 'Federation Admin',
            'federationEnabled' => $federationEnabled,
            'tenantSettings' => $tenantSettings,
            'partnerships' => $partnerships,
            'activePartnerships' => $activePartnerships,
            'pendingPartnerships' => $pendingPartnerships,
            'stats' => $stats,
            'auditLogs' => $auditLogs,
            'optedInUsers' => $optedInUsers,
            'activityTrends' => $activityTrends,
            'topUsers' => $topUsers,
            'basePath' => $basePath
        ]);
    }

    /**
     * Get federation statistics
     */
    private function getStats(int $tenantId): array
    {
        $stats = [
            'total_users_opted_in' => 0,
            'total_messages_sent' => 0,
            'total_messages_received' => 0,
            'total_transactions' => 0,
            'total_hours_sent' => 0,
            'total_hours_received' => 0,
            'active_partnerships' => 0,
            'pending_partnerships' => 0,
        ];

        try {
            // Users opted in
            $result = Database::query(
                "SELECT COUNT(*) as count FROM federation_user_settings
                 WHERE tenant_id = ? AND federation_optin = 1",
                [$tenantId]
            )->fetch();
            $stats['total_users_opted_in'] = $result['count'] ?? 0;

            // Messages sent (federated, from our tenant)
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages m
                 INNER JOIN users u ON m.sender_id = u.id
                 WHERE u.tenant_id = ? AND m.is_federated = 1",
                [$tenantId]
            )->fetch();
            $stats['total_messages_sent'] = $result['count'] ?? 0;

            // Messages received (federated, to our tenant)
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages m
                 INNER JOIN users u ON m.receiver_id = u.id
                 WHERE u.tenant_id = ? AND m.is_federated = 1",
                [$tenantId]
            )->fetch();
            $stats['total_messages_received'] = $result['count'] ?? 0;

            // Transactions sent
            $result = Database::query(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                 FROM transactions t
                 INNER JOIN users u ON t.sender_id = u.id
                 WHERE u.tenant_id = ? AND t.is_federated = 1 AND t.status = 'completed'",
                [$tenantId]
            )->fetch();
            $stats['total_hours_sent'] = (float)($result['total'] ?? 0);

            // Transactions received
            $result = Database::query(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                 FROM transactions t
                 INNER JOIN users u ON t.receiver_id = u.id
                 WHERE u.tenant_id = ? AND t.is_federated = 1 AND t.status = 'completed'",
                [$tenantId]
            )->fetch();
            $stats['total_transactions'] = ($stats['total_hours_sent'] > 0 ? $result['count'] : 0) + ($result['count'] ?? 0);
            $stats['total_hours_received'] = (float)($result['total'] ?? 0);

            // Partnerships
            $result = Database::query(
                "SELECT
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                 FROM federation_partnerships
                 WHERE tenant_id = ? OR partner_tenant_id = ?",
                [$tenantId, $tenantId]
            )->fetch();
            $stats['active_partnerships'] = $result['active'] ?? 0;
            $stats['pending_partnerships'] = $result['pending'] ?? 0;

        } catch (\Exception $e) {
            error_log("Federation admin stats error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent audit logs
     */
    private function getRecentAuditLogs(int $tenantId): array
    {
        try {
            return Database::query(
                "SELECT * FROM federation_audit_log
                 WHERE source_tenant_id = ? OR target_tenant_id = ?
                 ORDER BY created_at DESC
                 LIMIT 20",
                [$tenantId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get opted-in users with details
     */
    private function getOptedInUsersCount(int $tenantId): array
    {
        try {
            $total = Database::query(
                "SELECT COUNT(*) as count FROM users WHERE tenant_id = ?",
                [$tenantId]
            )->fetch()['count'] ?? 0;

            $optedIn = Database::query(
                "SELECT COUNT(*) as count FROM federation_user_settings
                 WHERE tenant_id = ? AND federation_optin = 1",
                [$tenantId]
            )->fetch()['count'] ?? 0;

            return [
                'total' => $total,
                'opted_in' => $optedIn,
                'percentage' => $total > 0 ? round(($optedIn / $total) * 100, 1) : 0
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'opted_in' => 0, 'percentage' => 0];
        }
    }

    /**
     * Get activity trends for last 30 days
     */
    private function getActivityTrends(int $tenantId): array
    {
        $trends = [];

        try {
            // Messages per day
            $messages = Database::query(
                "SELECT DATE(m.created_at) as date, COUNT(*) as count
                 FROM messages m
                 INNER JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
                 WHERE u.tenant_id = ? AND m.is_federated = 1
                 AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(m.created_at)
                 ORDER BY date ASC",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Transactions per day
            $transactions = Database::query(
                "SELECT DATE(t.created_at) as date, COUNT(*) as count, SUM(t.amount) as hours
                 FROM transactions t
                 INNER JOIN users u ON (t.sender_id = u.id OR t.receiver_id = u.id)
                 WHERE u.tenant_id = ? AND t.is_federated = 1 AND t.status = 'completed'
                 AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(t.created_at)
                 ORDER BY date ASC",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $trends['messages'] = $messages;
            $trends['transactions'] = $transactions;

        } catch (\Exception $e) {
            error_log("Federation trends error: " . $e->getMessage());
        }

        return $trends;
    }

    /**
     * Get top active federation users
     */
    private function getTopActiveUsers(int $tenantId): array
    {
        try {
            return Database::query(
                "SELECT u.id, u.name, u.first_name, u.last_name, u.avatar_url,
                        fus.privacy_level,
                        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND is_federated = 1) as messages_sent,
                        (SELECT COUNT(*) FROM transactions WHERE sender_id = u.id AND is_federated = 1 AND status = 'completed') as transactions_sent
                 FROM users u
                 INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                 WHERE u.tenant_id = ? AND fus.federation_optin = 1
                 ORDER BY messages_sent + transactions_sent DESC
                 LIMIT 10",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Toggle federation for tenant via AJAX
     */
    public function toggleFederation()
    {
        $this->checkAdmin();
        header('Content-Type: application/json');

        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();
        $input = json_decode(file_get_contents('php://input'), true);
        $enabled = !empty($input['enabled']);

        try {
            // Check if settings exist
            $existing = Database::query(
                "SELECT id FROM federation_tenant_settings WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            if ($existing) {
                Database::query(
                    "UPDATE federation_tenant_settings SET federation_enabled = ?, updated_at = NOW() WHERE tenant_id = ?",
                    [$enabled ? 1 : 0, $tenantId]
                );
            } else {
                Database::query(
                    "INSERT INTO federation_tenant_settings (tenant_id, federation_enabled, created_at, updated_at)
                     VALUES (?, ?, NOW(), NOW())",
                    [$tenantId, $enabled ? 1 : 0]
                );
            }

            // Log the action
            \Nexus\Services\FederationAuditService::log(
                $enabled ? 'tenant_federation_enabled' : 'tenant_federation_disabled',
                $tenantId,
                null,
                $_SESSION['user_id'],
                []
            );

            echo json_encode([
                'success' => true,
                'message' => $enabled ? 'Federation enabled for your timebank.' : 'Federation disabled for your timebank.'
            ]);

        } catch (\Exception $e) {
            error_log("Federation toggle error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update federation status']);
        }
        exit;
    }

    /**
     * Update tenant federation settings via AJAX
     */
    public function updateSettings()
    {
        $this->checkAdmin();
        header('Content-Type: application/json');

        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();
        $input = json_decode(file_get_contents('php://input'), true);

        $membersEnabled = !empty($input['members_enabled']);
        $listingsEnabled = !empty($input['listings_enabled']);
        $eventsEnabled = !empty($input['events_enabled']);
        $groupsEnabled = !empty($input['groups_enabled']);
        $messagingEnabled = !empty($input['messaging_enabled']);
        $transactionsEnabled = !empty($input['transactions_enabled']);

        try {
            Database::query(
                "UPDATE federation_tenant_settings SET
                    members_visible = ?,
                    listings_visible = ?,
                    events_visible = ?,
                    groups_visible = ?,
                    messaging_enabled = ?,
                    transactions_enabled = ?,
                    updated_at = NOW()
                 WHERE tenant_id = ?",
                [
                    $membersEnabled ? 1 : 0,
                    $listingsEnabled ? 1 : 0,
                    $eventsEnabled ? 1 : 0,
                    $groupsEnabled ? 1 : 0,
                    $messagingEnabled ? 1 : 0,
                    $transactionsEnabled ? 1 : 0,
                    $tenantId
                ]
            );

            // Log the action
            \Nexus\Services\FederationAuditService::log(
                'tenant_settings_updated',
                $tenantId,
                null,
                $_SESSION['user_id'],
                $input
            );

            echo json_encode([
                'success' => true,
                'message' => 'Federation settings updated successfully.'
            ]);

        } catch (\Exception $e) {
            error_log("Federation settings update error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update settings']);
        }
        exit;
    }
}
