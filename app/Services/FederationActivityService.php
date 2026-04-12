<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationActivityService — Federation activity feed and notification tracking.
 *
 * Aggregates cross-tenant activity (messages, connections, transactions, partnerships)
 * from the federation_audit_log into a user-facing activity feed.
 */
class FederationActivityService
{
    public function __construct()
    {
    }

    /**
     * Get the federation activity feed for a user.
     *
     * Pulls from federation_audit_log entries relevant to the user's tenant,
     * as well as direct federation events (messages, connections, transactions).
     */
    public static function getActivityFeed(int $userId, int $limit = 50, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();
        $limit = min(max($limit, 1), 100);
        $offset = max($offset, 0);

        try {
            $rows = DB::select(
                "SELECT id, action_type as type, category, data, actor_user_id,
                        actor_name, source_tenant_id, target_tenant_id, created_at
                 FROM federation_audit_log
                 WHERE (source_tenant_id = ? OR target_tenant_id = ?)
                   AND level IN ('info', 'warning')
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $tenantId, $limit, $offset]
            );

            return array_map(function ($row) use ($tenantId) {
                $data = is_string($row->data) ? (json_decode($row->data, true) ?: []) : [];
                $isIncoming = ((int) $row->target_tenant_id === $tenantId);

                // Build human-readable title from action type
                $title = self::buildActivityTitle($row->type, $row->category, $isIncoming);
                $subtitle = $row->actor_name ?? 'Federation Network';

                // Fetch partner tenant name if available
                $partnerTenantId = $isIncoming ? $row->source_tenant_id : $row->target_tenant_id;
                if ($partnerTenantId) {
                    $tenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [$partnerTenantId]);
                    if ($tenant) {
                        $subtitle = $tenant->name;
                    }
                }

                return [
                    'id' => (int) $row->id,
                    'type' => $row->type ?? 'activity',
                    'category' => $row->category ?? 'system',
                    'title' => $title,
                    'description' => $data['description'] ?? ($data['preview'] ?? ''),
                    'subtitle' => $subtitle,
                    'preview' => $data['preview'] ?? null,
                    'timestamp' => $row->created_at,
                    'actor_user_id' => $row->actor_user_id ? (int) $row->actor_user_id : null,
                    'actor_name' => $row->actor_name,
                    'partner_tenant_id' => $partnerTenantId ? (int) $partnerTenantId : null,
                ];
            }, $rows);
        } catch (\Exception $e) {
            Log::error('[FederationActivity] getActivityFeed failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get unread federation activity count for a user.
     *
     * Tracks read-state via a per-user cache key storing the timestamp of when
     * the user last viewed the activity feed.
     */
    public static function getUnreadCount(int $userId): int
    {
        $tenantId = TenantContext::getId();

        try {
            $lastReadAt = Cache::get("federation_activity_read:{$userId}");

            $sql = "SELECT COUNT(*) as cnt FROM federation_audit_log
                 WHERE (source_tenant_id = ? OR target_tenant_id = ?)
                   AND level IN ('info', 'warning')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $params = [$tenantId, $tenantId];

            if ($lastReadAt) {
                $sql .= " AND created_at > ?";
                $params[] = $lastReadAt;
            }

            $row = DB::selectOne($sql, $params);

            return (int) ($row->cnt ?? 0);
        } catch (\Exception $e) {
            Log::error('[FederationActivity] getUnreadCount failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Mark all federation activity as read for a user.
     */
    public static function markAllRead(int $userId): void
    {
        // Store for 30 days (longer than the 7-day unread window)
        Cache::put("federation_activity_read:{$userId}", now()->toDateTimeString(), 60 * 60 * 24 * 30);
    }

    /**
     * Get activity statistics summary for a user.
     */
    public static function getActivityStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $stats = [
                'total_activities' => 0,
                'activities_this_week' => 0,
                'activities_this_month' => 0,
                'by_category' => [],
            ];

            $total = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_audit_log WHERE source_tenant_id = ? OR target_tenant_id = ?",
                [$tenantId, $tenantId]
            );
            $stats['total_activities'] = (int) ($total->cnt ?? 0);

            $thisWeek = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_audit_log
                 WHERE (source_tenant_id = ? OR target_tenant_id = ?)
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId, $tenantId]
            );
            $stats['activities_this_week'] = (int) ($thisWeek->cnt ?? 0);

            $thisMonth = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM federation_audit_log
                 WHERE (source_tenant_id = ? OR target_tenant_id = ?)
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$tenantId, $tenantId]
            );
            $stats['activities_this_month'] = (int) ($thisMonth->cnt ?? 0);

            $categories = DB::select(
                "SELECT category, COUNT(*) as cnt FROM federation_audit_log
                 WHERE source_tenant_id = ? OR target_tenant_id = ?
                 GROUP BY category ORDER BY cnt DESC",
                [$tenantId, $tenantId]
            );
            foreach ($categories as $cat) {
                $stats['by_category'][$cat->category] = (int) $cat->cnt;
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('[FederationActivity] getActivityStats failed', ['error' => $e->getMessage()]);
            return [
                'total_activities' => 0,
                'activities_this_week' => 0,
                'activities_this_month' => 0,
                'by_category' => [],
            ];
        }
    }

    /**
     * Build a human-readable title from action type and category.
     */
    private static function buildActivityTitle(string $actionType, string $category, bool $isIncoming): string
    {
        $titles = [
            'partnership_requested' => $isIncoming ? 'New partnership request received' : 'Partnership request sent',
            'partnership_approved' => 'Partnership approved',
            'partnership_rejected' => 'Partnership request declined',
            'api_message_sent' => $isIncoming ? 'New federated message received' : 'Federated message sent',
            'api_transaction_initiated' => $isIncoming ? 'Cross-community transaction received' : 'Cross-community transaction sent',
            'member_search' => 'Cross-community member search',
            'listing_viewed' => 'Federation listing viewed',
            'connection_request' => $isIncoming ? 'New federation connection request' : 'Federation connection request sent',
        ];

        return $titles[$actionType] ?? ucfirst(str_replace('_', ' ', $actionType));
    }
}
