<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\MemberRankingService;

class FeedSidebarApiController extends BaseApiController
{
    /**
     * GET /api/v2/community/stats
     * Tenant-scoped community statistics
     */
    public function communityStats(): void
    {
        $tenantId = TenantContext::getId();

        try {
            $members = Database::query(
                "SELECT COUNT(*) FROM users WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn() ?: 0;

            $listings = Database::query(
                "SELECT COUNT(*) FROM listings WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            )->fetchColumn() ?: 0;

            $events = 0;
            try {
                $events = Database::query(
                    "SELECT COUNT(*) FROM events WHERE tenant_id = ?",
                    [$tenantId]
                )->fetchColumn() ?: 0;
            } catch (\Exception $e) { /* table may not exist */ }

            $groups = 0;
            try {
                $groups = Database::query(
                    "SELECT COUNT(*) FROM `groups` WHERE tenant_id = ?",
                    [$tenantId]
                )->fetchColumn() ?: 0;
            } catch (\Exception $e) { /* table may not exist */ }

            $this->respondWithData([
                'members' => (int) $members,
                'listings' => (int) $listings,
                'events' => (int) $events,
                'groups' => (int) $groups,
            ]);
        } catch (\Throwable $e) {
            error_log("Community stats error: " . $e->getMessage());
            $this->respondWithErrors([['code' => 'INTERNAL_ERROR', 'message' => 'Failed to load community stats']], 500);
        }
    }

    /**
     * GET /api/v2/members/suggested
     * CommunityRank-powered member suggestions, excluding connected users
     */
    public function suggestedMembers(): void
    {
        $userId = $this->requireUserId();
        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('limit', 5, 1, 20);

        try {
            // Get IDs of users already connected
            $connectedIds = [];
            try {
                $connStmt = Database::query(
                    "SELECT CASE WHEN requester_id = ? THEN receiver_id ELSE requester_id END as connected_id
                     FROM connections
                     WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'",
                    [$userId, $userId, $userId]
                );
                $connectedIds = array_column($connStmt->fetchAll(\PDO::FETCH_ASSOC), 'connected_id');
            } catch (\Exception $e) { /* connections table may not exist */ }

            $connectedIds[] = $userId; // exclude self

            // Use CommunityRank if available
            if (class_exists(MemberRankingService::class) && MemberRankingService::isEnabled()) {
                $rankQuery = MemberRankingService::buildRankedQuery($userId, ['limit' => $limit * 2]);
                $members = Database::query($rankQuery['sql'], $rankQuery['params'])->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $members = Database::query(
                    "SELECT id, first_name, last_name, organization_name, profile_type,
                            avatar_url, location, last_active_at
                     FROM users
                     WHERE tenant_id = ? AND status = 'active'
                     ORDER BY last_active_at DESC, created_at DESC
                     LIMIT ?",
                    [$tenantId, $limit * 2]
                )->fetchAll(\PDO::FETCH_ASSOC);
            }

            // Filter out connected users and self
            $filtered = [];
            foreach ($members as $m) {
                if (!in_array((int) $m['id'], array_map('intval', $connectedIds))) {
                    $filtered[] = [
                        'id' => (int) $m['id'],
                        'first_name' => $m['first_name'] ?? '',
                        'last_name' => $m['last_name'] ?? '',
                        'organization_name' => $m['organization_name'] ?? null,
                        'profile_type' => $m['profile_type'] ?? 'individual',
                        'avatar_url' => $m['avatar_url'] ?? null,
                        'location' => $m['location'] ?? null,
                        'is_online' => !empty($m['last_active_at']) && strtotime($m['last_active_at']) > strtotime('-5 minutes'),
                        'is_recent' => !empty($m['last_active_at']) && strtotime($m['last_active_at']) > strtotime('-24 hours'),
                    ];
                    if (count($filtered) >= $limit) break;
                }
            }

            $this->respondWithData($filtered);
        } catch (\Throwable $e) {
            error_log("Suggested members error: " . $e->getMessage());
            $this->respondWithErrors([['code' => 'INTERNAL_ERROR', 'message' => 'Failed to load suggestions']], 500);
        }
    }

    /**
     * GET /api/v2/feed/sidebar
     * Aggregated sidebar data for the feed page
     */
    public function sidebar(): void
    {
        $userId = $this->getOptionalUserId();
        $tenantId = TenantContext::getId();

        $data = [];

        // 1. Community stats
        try {
            $data['community_stats'] = [
                'members' => (int) Database::query("SELECT COUNT(*) FROM users WHERE tenant_id = ?", [$tenantId])->fetchColumn(),
                'listings' => (int) Database::query("SELECT COUNT(*) FROM listings WHERE tenant_id = ? AND status = 'active'", [$tenantId])->fetchColumn(),
            ];
            try { $data['community_stats']['events'] = (int) Database::query("SELECT COUNT(*) FROM events WHERE tenant_id = ?", [$tenantId])->fetchColumn(); } catch (\Exception $e) { $data['community_stats']['events'] = 0; }
            try { $data['community_stats']['groups'] = (int) Database::query("SELECT COUNT(*) FROM `groups` WHERE tenant_id = ?", [$tenantId])->fetchColumn(); } catch (\Exception $e) { $data['community_stats']['groups'] = 0; }
        } catch (\Throwable $e) {
            $data['community_stats'] = ['members' => 0, 'listings' => 0, 'events' => 0, 'groups' => 0];
        }

        // 2. Top categories
        try {
            $data['top_categories'] = Database::query(
                "SELECT c.id, c.name, c.slug, c.color, COUNT(l.id) as listing_count
                 FROM categories c
                 INNER JOIN listings l ON l.category_id = c.id AND l.tenant_id = ? AND l.status = 'active'
                 WHERE c.tenant_id = ? AND c.type = 'listing'
                 GROUP BY c.id
                 HAVING listing_count > 0
                 ORDER BY listing_count DESC
                 LIMIT 8",
                [$tenantId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $data['top_categories'] = [];
        }

        // 3. Upcoming events
        try {
            $data['upcoming_events'] = Database::query(
                "SELECT id, title, start_time, location FROM events
                 WHERE tenant_id = ? AND start_time >= NOW()
                 ORDER BY start_time LIMIT 3",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $data['upcoming_events'] = [];
        }

        // 4. Popular groups
        try {
            $data['popular_groups'] = Database::query(
                "SELECT g.id, g.name, g.description, g.image_url, COUNT(gm.id) as member_count
                 FROM `groups` g
                 LEFT JOIN group_members gm ON g.id = gm.group_id
                 WHERE g.tenant_id = ? AND g.is_active = 1
                 GROUP BY g.id
                 ORDER BY member_count DESC, g.created_at DESC
                 LIMIT 3",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $data['popular_groups'] = [];
        }

        // 5. Suggested listings (for logged-in users)
        if ($userId) {
            try {
                $data['suggested_listings'] = Database::query(
                    "SELECT l.id, l.title, l.type, l.image_url,
                            COALESCE(NULLIF(u.name, ''), CONCAT(u.first_name, ' ', u.last_name)) as owner_name
                     FROM listings l JOIN users u ON l.user_id = u.id
                     WHERE l.tenant_id = ? AND l.user_id != ? AND l.status = 'active'
                     ORDER BY l.created_at DESC LIMIT 4",
                    [$tenantId, $userId]
                )->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $data['suggested_listings'] = [];
            }

            // 6. Friends (connections)
            try {
                $data['friends'] = Database::query(
                    "SELECT u.id, u.first_name, u.last_name, u.organization_name, u.profile_type,
                            u.avatar_url, u.location, u.last_active_at
                     FROM connections c
                     JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
                     WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'
                     ORDER BY u.last_active_at DESC
                     LIMIT 8",
                    [$userId, $userId, $userId]
                )->fetchAll(\PDO::FETCH_ASSOC);

                // Add online status
                foreach ($data['friends'] as &$friend) {
                    $friend['is_online'] = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
                    $friend['is_recent'] = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
                }
                unset($friend);
            } catch (\Throwable $e) {
                $data['friends'] = [];
            }

            // 7. Profile stats
            try {
                $data['profile_stats'] = Database::query(
                    "SELECT
                        (SELECT COUNT(*) FROM listings WHERE user_id = ? AND tenant_id = ?) as total_listings,
                        (SELECT COUNT(*) FROM listings WHERE user_id = ? AND tenant_id = ? AND type = 'offer') as offers,
                        (SELECT COUNT(*) FROM listings WHERE user_id = ? AND tenant_id = ? AND type = 'request') as requests,
                        (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = ? AND tenant_id = ?) as hours_given,
                        (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = ? AND tenant_id = ?) as hours_received",
                    [$userId, $tenantId, $userId, $tenantId, $userId, $tenantId, $userId, $tenantId, $userId, $tenantId]
                )->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $data['profile_stats'] = null;
            }

            // 8. Suggested members (People You May Know)
            try {
                $connectedIds = [$userId];
                try {
                    $connStmt = Database::query(
                        "SELECT CASE WHEN requester_id = ? THEN receiver_id ELSE requester_id END as cid
                         FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'",
                        [$userId, $userId, $userId]
                    );
                    foreach ($connStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                        $connectedIds[] = (int) $row['cid'];
                    }
                } catch (\Exception $e) {}

                $placeholders = implode(',', array_fill(0, count($connectedIds), '?'));
                $params = array_merge([$tenantId], $connectedIds);

                $data['suggested_members'] = Database::query(
                    "SELECT id, first_name, last_name, organization_name, profile_type,
                            avatar_url, location, last_active_at
                     FROM users
                     WHERE tenant_id = ? AND id NOT IN ($placeholders) AND status = 'active'
                     ORDER BY last_active_at DESC, created_at DESC
                     LIMIT 5",
                    $params
                )->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($data['suggested_members'] as &$m) {
                    $m['is_online'] = !empty($m['last_active_at']) && strtotime($m['last_active_at']) > strtotime('-5 minutes');
                    $m['is_recent'] = !empty($m['last_active_at']) && strtotime($m['last_active_at']) > strtotime('-24 hours');
                }
                unset($m);
            } catch (\Throwable $e) {
                $data['suggested_members'] = [];
            }
        }

        $this->respondWithData($data);
    }
}
