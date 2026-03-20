<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * FeedActivityService — Laravel DI service for the denormalized feed_activity table.
 *
 * Every content creation (post, listing, event, poll, goal, review, job,
 * challenge, volunteer) records a row here. FeedService reads from this
 * single table instead of querying 9 separate source tables.
 */
class FeedActivityService
{
    /**
     * Valid source types that can appear in the feed.
     */
    private const VALID_TYPES = [
        'post', 'listing', 'event', 'poll', 'goal',
        'review', 'job', 'challenge', 'volunteer',
        'blog', 'discussion',
    ];

    public function __construct()
    {
    }

    /**
     * Get activity feed for a user (timeline).
     */
    public function getActivity(int $tenantId, int $userId, int $limit = 20): array
    {
        $rows = DB::select(
            "SELECT fa.*, COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                    u.avatar_url as author_avatar
             FROM feed_activity fa
             JOIN users u ON fa.user_id = u.id
             WHERE fa.tenant_id = ? AND fa.user_id = ? AND fa.is_visible = 1
             ORDER BY fa.created_at DESC
             LIMIT ?",
            [$tenantId, $userId, $limit]
        );

        return array_map(fn($r) => (array) $r, $rows);
    }

    /**
     * Log an activity entry.
     */
    public function logActivity(int $tenantId, int $userId, string $type, array $data = []): bool
    {
        try {
            DB::insert(
                "INSERT INTO feed_activity (tenant_id, user_id, source_type, source_id, title, content, image_url, metadata, is_visible, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [
                    $tenantId,
                    $userId,
                    $type,
                    $data['source_id'] ?? 0,
                    $data['title'] ?? null,
                    $data['content'] ?? null,
                    $data['image_url'] ?? null,
                    isset($data['metadata']) ? json_encode($data['metadata']) : null,
                ]
            );
            return true;
        } catch (\Exception $e) {
            error_log("FeedActivityService::logActivity error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get timeline for a tenant.
     */
    public function getTimeline(int $tenantId, int $limit = 50): array
    {
        $rows = DB::select(
            "SELECT fa.*, COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                    u.avatar_url as author_avatar
             FROM feed_activity fa
             JOIN users u ON fa.user_id = u.id
             WHERE fa.tenant_id = ? AND fa.is_visible = 1
             ORDER BY fa.created_at DESC
             LIMIT ?",
            [$tenantId, $limit]
        );

        return array_map(fn($r) => (array) $r, $rows);
    }

    /**
     * Record a feed activity entry.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for idempotency — safe to call
     * multiple times for the same (tenant_id, source_type, source_id).
     */
    public function recordActivity(int $tenantId, int $userId, string $sourceType, int $sourceId, array $data = []): void
    {
        if (!in_array($sourceType, self::VALID_TYPES, true)) {
            error_log("FeedActivityService::recordActivity invalid source_type: {$sourceType}");
            return;
        }

        $title = $data['title'] ?? null;
        $content = $data['content'] ?? null;
        $imageUrl = $data['image_url'] ?? null;
        $groupId = !empty($data['group_id']) ? (int) $data['group_id'] : null;
        $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;
        $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');

        DB::statement(
            "INSERT INTO feed_activity
                (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                user_id    = VALUES(user_id),
                group_id   = VALUES(group_id),
                title      = VALUES(title),
                content    = VALUES(content),
                image_url  = VALUES(image_url),
                metadata   = VALUES(metadata),
                is_visible = 1,
                created_at = VALUES(created_at)",
            [
                $tenantId,
                $userId,
                $sourceType,
                $sourceId,
                $groupId,
                $title,
                $content,
                $imageUrl,
                $metadata,
                $createdAt,
            ]
        );
    }

    /**
     * Hard-delete an activity row.
     */
    public function removeActivity(string $sourceType, int $sourceId): void
    {
        $tenantId = TenantContext::getId();

        DB::delete(
            "DELETE FROM feed_activity WHERE tenant_id = ? AND source_type = ? AND source_id = ?",
            [$tenantId, $sourceType, $sourceId]
        );
    }

    /**
     * Hide an activity by setting is_visible = 0.
     */
    public function hideActivity(string $sourceType, int $sourceId): void
    {
        $tenantId = TenantContext::getId();

        DB::update(
            "UPDATE feed_activity SET is_visible = 0 WHERE tenant_id = ? AND source_type = ? AND source_id = ?",
            [$tenantId, $sourceType, $sourceId]
        );
    }

    /**
     * Show a previously hidden activity by restoring is_visible = 1.
     */
    public function showActivity(string $sourceType, int $sourceId): void
    {
        $tenantId = TenantContext::getId();

        DB::update(
            "UPDATE feed_activity SET is_visible = 1 WHERE tenant_id = ? AND source_type = ? AND source_id = ?",
            [$tenantId, $sourceType, $sourceId]
        );
    }
}
