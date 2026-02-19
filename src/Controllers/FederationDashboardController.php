<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;

/**
 * Federation Dashboard Controller
 *
 * Personal dashboard for user's federation activity
 */
class FederationDashboardController
{
    /**
     * Display user's federation dashboard
     */
    public function index()
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
                'pageTitle' => 'Federation Not Available'
            ]);
            return;
        }

        // Get user's federation settings
        $userSettings = Database::query(
            "SELECT * FROM federation_user_settings WHERE user_id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        $isOptedIn = !empty($userSettings['federation_optin']);

        // If not opted in, redirect to onboarding
        if (!$isOptedIn) {
            header('Location: ' . $basePath . '/federation/onboarding');
            exit;
        }

        // Get user profile
        $userProfile = Database::query(
            "SELECT id, name, first_name, last_name, avatar_url, bio, skills, location
             FROM users WHERE id = ?",
            [$userId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Get statistics
        $stats = $this->getUserStats($userId, $tenantId);

        // Get recent activity
        $recentActivity = $this->getRecentActivity($userId, $tenantId);

        // Get partner timebanks
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);
        $activePartnerships = array_filter($partnerships, fn($p) => $p['status'] === 'active');

        // Get federated groups user is member of
        $federatedGroups = $this->getFederatedGroups($userId, $tenantId);

        // Get upcoming federated events user is attending
        $upcomingEvents = $this->getUpcomingEvents($userId, $tenantId);

        // Get unread federated messages count
        $unreadMessages = $this->getUnreadMessageCount($userId);

        \Nexus\Core\SEO::setTitle('My Federation Dashboard');
        \Nexus\Core\SEO::setDescription('View your federation activity, stats, and connections with partner timebanks.');

        View::render('federation/dashboard', [
            'pageTitle' => 'My Federation',
            'userSettings' => $userSettings,
            'userProfile' => $userProfile,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'partnerCount' => count($activePartnerships),
            'federatedGroups' => $federatedGroups,
            'upcomingEvents' => $upcomingEvents,
            'unreadMessages' => $unreadMessages,
            'basePath' => $basePath
        ]);
    }

    /**
     * Get user's federation statistics
     */
    private function getUserStats(int $userId, int $tenantId): array
    {
        $stats = [
            'messages_sent' => 0,
            'messages_received' => 0,
            'transactions_sent' => 0,
            'transactions_received' => 0,
            'hours_given' => 0,
            'hours_received' => 0,
            'profile_views' => 0,
            'groups_joined' => 0,
            'events_attended' => 0,
        ];

        try {
            // Messages sent (federated)
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages
                 WHERE sender_id = ? AND is_federated = 1",
                [$userId]
            )->fetch();
            $stats['messages_sent'] = $result['count'] ?? 0;

            // Messages received (federated)
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages
                 WHERE receiver_id = ? AND is_federated = 1",
                [$userId]
            )->fetch();
            $stats['messages_received'] = $result['count'] ?? 0;

            // Transactions sent
            $result = Database::query(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                 FROM transactions
                 WHERE sender_id = ? AND is_federated = 1 AND status = 'completed'",
                [$userId]
            )->fetch();
            $stats['transactions_sent'] = $result['count'] ?? 0;
            $stats['hours_given'] = (float)($result['total'] ?? 0);

            // Transactions received
            $result = Database::query(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                 FROM transactions
                 WHERE receiver_id = ? AND is_federated = 1 AND status = 'completed'",
                [$userId]
            )->fetch();
            $stats['transactions_received'] = $result['count'] ?? 0;
            $stats['hours_received'] = (float)($result['total'] ?? 0);

            // Federated groups joined
            $result = Database::query(
                "SELECT COUNT(*) as count FROM group_members gm
                 INNER JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.user_id = ? AND g.tenant_id != ? AND gm.status = 'approved'",
                [$userId, $tenantId]
            )->fetch();
            $stats['groups_joined'] = $result['count'] ?? 0;

            // Federated events RSVPed
            $result = Database::query(
                "SELECT COUNT(*) as count FROM event_rsvps er
                 INNER JOIN events e ON er.event_id = e.id
                 WHERE er.user_id = ? AND er.is_federated = 1 AND er.status = 'going'",
                [$userId]
            )->fetch();
            $stats['events_attended'] = $result['count'] ?? 0;

        } catch (\Exception $e) {
            error_log("Federation dashboard stats error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent federation activity
     */
    private function getRecentActivity(int $userId, int $tenantId): array
    {
        $activity = [];

        try {
            // Recent federated messages
            $messages = Database::query(
                "SELECT 'message' as type, m.created_at, m.subject,
                        CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as direction,
                        u.name as other_user, u.avatar_url, t.name as tenant_name
                 FROM messages m
                 INNER JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
                 INNER JOIN tenants t ON u.tenant_id = t.id
                 WHERE (m.sender_id = ? OR m.receiver_id = ?) AND m.is_federated = 1
                 ORDER BY m.created_at DESC
                 LIMIT 5",
                [$userId, $userId, $userId, $userId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($messages as $m) {
                $activity[] = [
                    'type' => 'message',
                    'direction' => $m['direction'],
                    'date' => $m['created_at'],
                    'title' => $m['direction'] === 'sent' ? 'Message sent' : 'Message received',
                    'description' => ($m['direction'] === 'sent' ? 'To ' : 'From ') . $m['other_user'],
                    'subtitle' => $m['tenant_name'],
                    'icon' => 'fa-envelope',
                    'avatar' => $m['avatar_url']
                ];
            }

            // Recent federated transactions
            $transactions = Database::query(
                "SELECT 'transaction' as type, tr.created_at, tr.amount, tr.description,
                        CASE WHEN tr.sender_id = ? THEN 'sent' ELSE 'received' END as direction,
                        u.name as other_user, u.avatar_url, t.name as tenant_name
                 FROM transactions tr
                 INNER JOIN users u ON (CASE WHEN tr.sender_id = ? THEN tr.receiver_id ELSE tr.sender_id END) = u.id
                 INNER JOIN tenants t ON u.tenant_id = t.id
                 WHERE (tr.sender_id = ? OR tr.receiver_id = ?) AND tr.is_federated = 1 AND tr.status = 'completed'
                 ORDER BY tr.created_at DESC
                 LIMIT 5",
                [$userId, $userId, $userId, $userId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($transactions as $t) {
                $activity[] = [
                    'type' => 'transaction',
                    'direction' => $t['direction'],
                    'date' => $t['created_at'],
                    'title' => ($t['direction'] === 'sent' ? 'Sent ' : 'Received ') . number_format($t['amount'], 1) . ' hours',
                    'description' => ($t['direction'] === 'sent' ? 'To ' : 'From ') . $t['other_user'],
                    'subtitle' => $t['tenant_name'],
                    'icon' => $t['direction'] === 'sent' ? 'fa-arrow-up' : 'fa-arrow-down',
                    'avatar' => $t['avatar_url']
                ];
            }

            // Sort by date
            usort($activity, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

            // Limit to 10
            $activity = array_slice($activity, 0, 10);

        } catch (\Exception $e) {
            error_log("Federation dashboard activity error: " . $e->getMessage());
        }

        return $activity;
    }

    /**
     * Get federated groups user is member of
     */
    private function getFederatedGroups(int $userId, int $tenantId): array
    {
        try {
            return Database::query(
                "SELECT g.id, g.name, g.description, t.name as tenant_name,
                        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND status = 'approved') as member_count
                 FROM group_members gm
                 INNER JOIN `groups` g ON gm.group_id = g.id
                 INNER JOIN tenants t ON g.tenant_id = t.id
                 WHERE gm.user_id = ? AND g.tenant_id != ? AND gm.status = 'approved'
                 ORDER BY g.name
                 LIMIT 5",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get upcoming federated events user is attending
     */
    private function getUpcomingEvents(int $userId, int $tenantId): array
    {
        try {
            return Database::query(
                "SELECT e.id, e.title, e.start_time, e.location, t.name as tenant_name
                 FROM event_rsvps er
                 INNER JOIN events e ON er.event_id = e.id
                 INNER JOIN tenants t ON e.tenant_id = t.id
                 WHERE er.user_id = ? AND er.is_federated = 1 AND er.status = 'going'
                 AND e.start_time > NOW()
                 ORDER BY e.start_time ASC
                 LIMIT 3",
                [$userId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get unread federated message count
     */
    private function getUnreadMessageCount(int $userId): int
    {
        try {
            $result = Database::query(
                "SELECT COUNT(*) as count FROM messages
                 WHERE receiver_id = ? AND is_federated = 1 AND is_read = 0",
                [$userId]
            )->fetch();
            return $result['count'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
