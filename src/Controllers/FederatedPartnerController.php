<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;

/**
 * Federated Partner Controller
 *
 * View detailed profiles of partner timebanks
 */
class FederatedPartnerController
{
    /**
     * Show partner timebank profile
     */
    public function show($partnerId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $tenantId = TenantContext::getId();
        $userId = $_SESSION['user_id'];
        $basePath = TenantContext::getBasePath();

        // Check if federation is enabled
        $federationEnabled = FederationFeatureService::isGloballyEnabled()
            && FederationFeatureService::isTenantWhitelisted($tenantId)
            && FederationFeatureService::isTenantFederationEnabled($tenantId);

        if (!$federationEnabled) {
            View::render('federation/not-available', [
                'pageTitle' => 'Partner Timebanks Not Available'
            ]);
            return;
        }

        // Verify this is actually a partner
        $partnership = $this->getPartnership($tenantId, (int)$partnerId);
        if (!$partnership || $partnership['status'] !== 'active') {
            http_response_code(404);
            View::render('errors/404', [
                'pageTitle' => 'Partner Not Found'
            ]);
            return;
        }

        // Get partner tenant details
        $partner = Database::query(
            "SELECT id, name, slug, domain, og_image_url, description, created_at
             FROM tenants WHERE id = ?",
            [(int)$partnerId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$partner) {
            http_response_code(404);
            View::render('errors/404', [
                'pageTitle' => 'Partner Not Found'
            ]);
            return;
        }

        // Get partnership details and enabled features
        $features = [
            'members' => (bool)($partnership['profiles_enabled'] ?? false),
            'listings' => (bool)($partnership['listings_enabled'] ?? false),
            'events' => (bool)($partnership['events_enabled'] ?? false),
            'groups' => (bool)($partnership['groups_enabled'] ?? false),
            'messaging' => (bool)($partnership['messaging_enabled'] ?? false),
            'transactions' => (bool)($partnership['transactions_enabled'] ?? false),
        ];

        // Get statistics for this partner
        $stats = $this->getPartnerStats((int)$partnerId, $features);

        // Get recent activity with this partner
        $recentActivity = $this->getRecentActivity($tenantId, (int)$partnerId, $userId);

        // Get partnership history
        $partnershipSince = $partnership['activated_at'] ?? $partnership['created_at'];

        // Check if current user has opted in
        $userFedSettings = Database::query(
            "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch();
        $userOptedIn = $userFedSettings && $userFedSettings['federation_optin'];

        // Get all active partner tenants for scope switcher
        $partnerTenants = Database::query("
            SELECT t.id, t.name
            FROM federation_partnerships fp
            JOIN tenants t ON (
                (fp.tenant_id = ? AND t.id = fp.partner_tenant_id) OR
                (fp.partner_tenant_id = ? AND t.id = fp.tenant_id)
            )
            WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?)
            AND fp.status = 'active'
            ORDER BY t.name
        ", [$tenantId, $tenantId, $tenantId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        $partnerCommunities = array_map(fn($t) => [
            'id' => $t['id'],
            'name' => $t['name']
        ], $partnerTenants);

        $currentScope = $_GET['scope'] ?? 'all';

        \Nexus\Core\SEO::setTitle($partner['name'] . ' - Partner Timebank');
        \Nexus\Core\SEO::setDescription('View details about our partner timebank ' . $partner['name'] . ' and explore available features.');

        $viewPath = 'federation/partner-profile';

        View::render($viewPath, [
            'pageTitle' => $partner['name'],
            'partner' => $partner,
            'partnership' => $partnership,
            'features' => $features,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'partnershipSince' => $partnershipSince,
            'userOptedIn' => $userOptedIn,
            'partnerCommunities' => $partnerCommunities,
            'currentScope' => $currentScope,
            'basePath' => $basePath
        ]);
    }

    /**
     * Get partnership between current tenant and partner
     */
    private function getPartnership(int $tenantId, int $partnerId): ?array
    {
        $partnership = Database::query(
            "SELECT * FROM federation_partnerships
             WHERE (tenant_id = ? AND partner_tenant_id = ?)
                OR (tenant_id = ? AND partner_tenant_id = ?)",
            [$tenantId, $partnerId, $partnerId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $partnership ?: null;
    }

    /**
     * Get statistics for a partner timebank
     */
    private function getPartnerStats(int $partnerId, array $features): array
    {
        $stats = [
            'members' => 0,
            'listings' => 0,
            'events' => 0,
            'groups' => 0,
            'total_hours_exchanged' => 0,
        ];

        try {
            // Count federated members from this partner
            if ($features['members']) {
                $result = Database::query(
                    "SELECT COUNT(DISTINCT u.id) as count
                     FROM users u
                     JOIN federation_user_settings fus ON u.id = fus.user_id
                     WHERE u.tenant_id = ?
                     AND u.status = 'active'
                     AND fus.federation_optin = 1
                     AND fus.profile_visible_federated = 1",
                    [$partnerId]
                )->fetch();
                $stats['members'] = $result['count'] ?? 0;
            }

            // Count federated listings
            if ($features['listings']) {
                $result = Database::query(
                    "SELECT COUNT(*) as count
                     FROM listings
                     WHERE tenant_id = ?
                     AND federated_visibility IN ('listed', 'bookable')
                     AND status = 'active'",
                    [$partnerId]
                )->fetch();
                $stats['listings'] = $result['count'] ?? 0;
            }

            // Count upcoming federated events
            if ($features['events']) {
                $result = Database::query(
                    "SELECT COUNT(*) as count
                     FROM events
                     WHERE tenant_id = ?
                     AND federated_visibility IN ('listed', 'joinable')
                     AND start_time >= NOW()",
                    [$partnerId]
                )->fetch();
                $stats['events'] = $result['count'] ?? 0;
            }

            // Count federated groups
            if ($features['groups']) {
                $result = Database::query(
                    "SELECT COUNT(*) as count
                     FROM `groups`
                     WHERE tenant_id = ?
                     AND federated_visibility IN ('listed', 'joinable')",
                    [$partnerId]
                )->fetch();
                $stats['groups'] = $result['count'] ?? 0;
            }

            // Get total hours exchanged with this partner
            if ($features['transactions']) {
                $tenantId = TenantContext::getId();
                $result = Database::query(
                    "SELECT COALESCE(SUM(amount), 0) as total
                     FROM transactions
                     WHERE status = 'completed'
                     AND is_federated = 1
                     AND ((sender_tenant_id = ? AND receiver_tenant_id = ?)
                          OR (sender_tenant_id = ? AND receiver_tenant_id = ?))",
                    [$tenantId, $partnerId, $partnerId, $tenantId]
                )->fetch();
                $stats['total_hours_exchanged'] = (float)($result['total'] ?? 0);
            }

        } catch (\Exception $e) {
            error_log("Partner stats error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent activity with this partner
     */
    private function getRecentActivity(int $tenantId, int $partnerId, int $userId): array
    {
        $activity = [];

        try {
            // Recent messages with this partner's members
            $messages = Database::query(
                "SELECT 'message' as type, m.created_at, m.subject,
                        u.first_name, u.last_name
                 FROM messages m
                 JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id) AND u.id != ?
                 WHERE m.is_federated = 1
                 AND ((m.sender_id = ? AND u.tenant_id = ?)
                      OR (m.receiver_id = ? AND u.tenant_id = ?))
                 ORDER BY m.created_at DESC
                 LIMIT 3",
                [$userId, $userId, $partnerId, $userId, $partnerId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($messages as $m) {
                $activity[] = [
                    'type' => 'message',
                    'date' => $m['created_at'],
                    'description' => 'Message with ' . $m['first_name'] . ' ' . substr($m['last_name'], 0, 1) . '.',
                    'icon' => 'fa-envelope'
                ];
            }

            // Recent transactions with this partner
            $transactions = Database::query(
                "SELECT 'transaction' as type, t.created_at, t.amount, t.description,
                        u.first_name, u.last_name,
                        CASE WHEN t.sender_id = ? THEN 'sent' ELSE 'received' END as direction
                 FROM transactions t
                 JOIN users u ON (t.sender_id = u.id OR t.receiver_id = u.id) AND u.id != ?
                 WHERE t.is_federated = 1
                 AND t.status = 'completed'
                 AND ((t.sender_id = ? AND t.receiver_tenant_id = ?)
                      OR (t.receiver_id = ? AND t.sender_tenant_id = ?))
                 ORDER BY t.created_at DESC
                 LIMIT 3",
                [$userId, $userId, $userId, $partnerId, $userId, $partnerId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($transactions as $t) {
                $direction = $t['direction'] === 'sent' ? 'to' : 'from';
                $activity[] = [
                    'type' => 'transaction',
                    'date' => $t['created_at'],
                    'description' => number_format($t['amount'], 1) . ' hours ' . $direction . ' ' . $t['first_name'] . ' ' . substr($t['last_name'], 0, 1) . '.',
                    'icon' => 'fa-exchange-alt'
                ];
            }

            // Sort by date descending
            usort($activity, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            // Limit to 5 most recent
            $activity = array_slice($activity, 0, 5);

        } catch (\Exception $e) {
            error_log("Partner activity error: " . $e->getMessage());
        }

        return $activity;
    }
}
