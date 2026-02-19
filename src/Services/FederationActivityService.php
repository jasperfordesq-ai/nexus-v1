<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Federation Activity Service
 *
 * Provides a unified activity feed combining messages, transactions,
 * and new partner availability for federation-opted users.
 */
class FederationActivityService
{
    /**
     * Get combined federation activity feed for a user
     *
     * @param int $userId The user ID
     * @param int $limit Maximum number of items to return
     * @param int $offset Pagination offset
     * @return array Activity feed items sorted by date
     */
    public static function getActivityFeed(int $userId, int $limit = 50, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();
        $activities = [];

        // Get recent federated messages received
        $messages = self::getRecentMessages($userId, $tenantId, $limit);
        foreach ($messages as $msg) {
            $activities[] = [
                'type' => 'message',
                'icon' => 'fa-envelope',
                'color' => '#3b82f6',
                'title' => 'New message from ' . ($msg['sender_name'] ?? 'A member'),
                'subtitle' => $msg['sender_tenant_name'] ?? 'Partner Timebank',
                'description' => $msg['subject'] ?? '',
                'preview' => substr($msg['body'] ?? '', 0, 100),
                'link' => '/federation/messages/thread/' . $msg['sender_user_id'] . '/' . $msg['sender_tenant_id'],
                'timestamp' => $msg['created_at'],
                'is_unread' => ($msg['status'] ?? '') === 'unread',
                'meta' => [
                    'sender_id' => $msg['sender_user_id'],
                    'sender_tenant_id' => $msg['sender_tenant_id'],
                    'message_id' => $msg['id']
                ]
            ];
        }

        // Get recent federated transactions
        $transactions = self::getRecentTransactions($userId, $tenantId, $limit);
        foreach ($transactions as $tx) {
            $isSent = ($tx['direction'] ?? '') === 'sent';
            $activities[] = [
                'type' => 'transaction',
                'icon' => $isSent ? 'fa-arrow-up' : 'fa-arrow-down',
                'color' => $isSent ? '#ef4444' : '#10b981',
                'title' => $isSent
                    ? 'Sent ' . number_format($tx['amount'], 1) . ' hour(s) to ' . ($tx['other_user_name'] ?? 'A member')
                    : 'Received ' . number_format($tx['amount'], 1) . ' hour(s) from ' . ($tx['other_user_name'] ?? 'A member'),
                'subtitle' => $tx['other_tenant_name'] ?? 'Partner Timebank',
                'description' => $tx['description'] ?? '',
                'preview' => null,
                'link' => '/federation/transactions',
                'timestamp' => $tx['created_at'],
                'is_unread' => false,
                'meta' => [
                    'amount' => $tx['amount'],
                    'direction' => $tx['direction'],
                    'transaction_id' => $tx['id']
                ]
            ];
        }

        // Get new partner timebanks (partnerships activated in last 30 days)
        $newPartners = self::getNewPartners($tenantId, $limit);
        foreach ($newPartners as $partner) {
            $activities[] = [
                'type' => 'new_partner',
                'icon' => 'fa-handshake',
                'color' => '#8b5cf6',
                'title' => 'New partner: ' . ($partner['partner_name'] ?? 'A timebank'),
                'subtitle' => self::getLevelName($partner['federation_level'] ?? 1),
                'description' => 'A new timebank has joined the federation network!',
                'preview' => null,
                'link' => '/federation',
                'timestamp' => $partner['updated_at'] ?? $partner['created_at'],
                'is_unread' => false,
                'meta' => [
                    'partner_tenant_id' => $partner['partner_tenant_id'],
                    'features' => self::getPartnerFeatures($partner)
                ]
            ];
        }

        // Sort all activities by timestamp descending
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Apply offset and limit
        return array_slice($activities, $offset, $limit);
    }

    /**
     * Get unread activity count for badge display
     */
    public static function getUnreadCount(int $userId): int
    {
        $tenantId = TenantContext::getId();
        $count = 0;

        // Count unread federated messages
        try {
            $result = Database::query("
                SELECT COUNT(*) as count
                FROM federation_messages
                WHERE receiver_tenant_id = ?
                AND receiver_user_id = ?
                AND direction = 'inbound'
                AND status = 'unread'
            ", [$tenantId, $userId])->fetch();
            $count += (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("FederationActivityService::getUnreadCount messages error: " . $e->getMessage());
        }

        return $count;
    }

    /**
     * Get activity summary stats for the user
     */
    public static function getActivityStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $stats = [
            'unread_messages' => 0,
            'total_messages' => 0,
            'transactions_sent' => 0,
            'transactions_received' => 0,
            'hours_sent' => 0,
            'hours_received' => 0,
            'partner_count' => 0
        ];

        try {
            // Messages
            $msgResult = Database::query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread
                FROM federation_messages
                WHERE receiver_tenant_id = ?
                AND receiver_user_id = ?
                AND direction = 'inbound'
            ", [$tenantId, $userId])->fetch();

            $stats['total_messages'] = (int)($msgResult['total'] ?? 0);
            $stats['unread_messages'] = (int)($msgResult['unread'] ?? 0);

            // Transactions
            $txResult = Database::query("
                SELECT
                    COUNT(CASE WHEN sender_user_id = ? THEN 1 END) as sent_count,
                    COALESCE(SUM(CASE WHEN sender_user_id = ? THEN amount END), 0) as sent_hours,
                    COUNT(CASE WHEN receiver_user_id = ? THEN 1 END) as received_count,
                    COALESCE(SUM(CASE WHEN receiver_user_id = ? THEN amount END), 0) as received_hours
                FROM federation_transactions
                WHERE ((sender_tenant_id = ? AND sender_user_id = ?)
                    OR (receiver_tenant_id = ? AND receiver_user_id = ?))
                AND status = 'completed'
            ", [$userId, $userId, $userId, $userId, $tenantId, $userId, $tenantId, $userId])->fetch();

            $stats['transactions_sent'] = (int)($txResult['sent_count'] ?? 0);
            $stats['transactions_received'] = (int)($txResult['received_count'] ?? 0);
            $stats['hours_sent'] = (float)($txResult['sent_hours'] ?? 0);
            $stats['hours_received'] = (float)($txResult['received_hours'] ?? 0);

            // Partner count
            $partnerResult = Database::query("
                SELECT COUNT(*) as count
                FROM federation_partnerships
                WHERE (tenant_id = ? OR partner_tenant_id = ?)
                AND status = 'active'
            ", [$tenantId, $tenantId])->fetch();

            $stats['partner_count'] = (int)($partnerResult['count'] ?? 0);

        } catch (\Exception $e) {
            error_log("FederationActivityService::getActivityStats error: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get recent federated messages for activity feed
     */
    private static function getRecentMessages(int $userId, int $tenantId, int $limit): array
    {
        try {
            $stmt = Database::query("
                SELECT fm.*,
                       u.name as sender_name,
                       t.name as sender_tenant_name
                FROM federation_messages fm
                LEFT JOIN users u ON fm.sender_user_id = u.id
                LEFT JOIN tenants t ON fm.sender_tenant_id = t.id
                WHERE fm.receiver_tenant_id = ?
                AND fm.receiver_user_id = ?
                AND fm.direction = 'inbound'
                ORDER BY fm.created_at DESC
                LIMIT ?
            ", [$tenantId, $userId, $limit]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederationActivityService::getRecentMessages error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent federated transactions for activity feed
     */
    private static function getRecentTransactions(int $userId, int $tenantId, int $limit): array
    {
        try {
            $stmt = Database::query("
                SELECT ft.*,
                       CASE WHEN ft.sender_user_id = ? THEN 'sent' ELSE 'received' END as direction,
                       CASE WHEN ft.sender_user_id = ?
                            THEN (SELECT name FROM users WHERE id = ft.receiver_user_id)
                            ELSE (SELECT name FROM users WHERE id = ft.sender_user_id)
                       END as other_user_name,
                       CASE WHEN ft.sender_user_id = ?
                            THEN (SELECT name FROM tenants WHERE id = ft.receiver_tenant_id)
                            ELSE (SELECT name FROM tenants WHERE id = ft.sender_tenant_id)
                       END as other_tenant_name
                FROM federation_transactions ft
                WHERE (ft.sender_tenant_id = ? AND ft.sender_user_id = ?)
                   OR (ft.receiver_tenant_id = ? AND ft.receiver_user_id = ?)
                ORDER BY ft.created_at DESC
                LIMIT ?
            ", [$userId, $userId, $userId, $tenantId, $userId, $tenantId, $userId, $limit]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederationActivityService::getRecentTransactions error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get newly activated partner timebanks
     */
    private static function getNewPartners(int $tenantId, int $limit): array
    {
        try {
            $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

            $stmt = Database::query("
                SELECT fp.*,
                       CASE WHEN fp.tenant_id = ?
                            THEN (SELECT name FROM tenants WHERE id = fp.partner_tenant_id)
                            ELSE (SELECT name FROM tenants WHERE id = fp.tenant_id)
                       END as partner_name,
                       CASE WHEN fp.tenant_id = ?
                            THEN fp.partner_tenant_id
                            ELSE fp.tenant_id
                       END as partner_tenant_id
                FROM federation_partnerships fp
                WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?)
                AND fp.status = 'active'
                AND fp.updated_at >= ?
                ORDER BY fp.updated_at DESC
                LIMIT ?
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $thirtyDaysAgo, $limit]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederationActivityService::getNewPartners error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get human-readable federation level name
     */
    private static function getLevelName(int $level): string
    {
        $levels = [
            1 => 'Discovery',
            2 => 'Social',
            3 => 'Economic',
            4 => 'Integrated'
        ];
        return $levels[$level] ?? 'Discovery';
    }

    /**
     * Get enabled features for a partnership
     */
    private static function getPartnerFeatures(array $partner): array
    {
        $features = [];
        if ($partner['profiles_enabled'] ?? false) $features[] = 'Members';
        if ($partner['listings_enabled'] ?? false) $features[] = 'Listings';
        if ($partner['events_enabled'] ?? false) $features[] = 'Events';
        if ($partner['groups_enabled'] ?? false) $features[] = 'Groups';
        if ($partner['messaging_enabled'] ?? false) $features[] = 'Messaging';
        if ($partner['transactions_enabled'] ?? false) $features[] = 'Transactions';
        return $features;
    }
}
