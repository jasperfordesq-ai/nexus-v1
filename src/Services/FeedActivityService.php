<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FeedActivityService - Manages the denormalized feed_activity table.
 *
 * Every content creation (post, listing, event, poll, goal, review, job,
 * challenge, volunteer) records a row here. FeedService reads from this
 * single table instead of querying 9 separate source tables.
 *
 * All methods are non-blocking: failures are logged but never prevent
 * the originating content operation from succeeding.
 */
class FeedActivityService
{
    /**
     * Valid source types that can appear in the feed.
     */
    private const VALID_TYPES = [
        'post', 'listing', 'event', 'poll', 'goal',
        'review', 'job', 'challenge', 'volunteer',
    ];

    /**
     * Record a new activity in the feed.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for idempotency — safe to call
     * multiple times for the same (tenant_id, source_type, source_id).
     *
     * @param int    $tenantId   Tenant scope
     * @param int    $userId     Author of the content
     * @param string $sourceType One of VALID_TYPES
     * @param int    $sourceId   PK of the source record
     * @param array  $data       Optional fields:
     *   - title       (string|null)
     *   - content     (string|null)
     *   - image_url   (string|null)
     *   - group_id    (int|null)
     *   - metadata    (array|null)  — will be JSON-encoded
     *   - created_at  (string|null) — defaults to NOW()
     */
    public static function recordActivity(
        int $tenantId,
        int $userId,
        string $sourceType,
        int $sourceId,
        array $data = []
    ): void {
        if (!in_array($sourceType, self::VALID_TYPES, true)) {
            error_log("FeedActivityService::recordActivity invalid source_type: {$sourceType}");
            return;
        }

        $db = Database::getConnection();

        $title = $data['title'] ?? null;
        $content = $data['content'] ?? null;
        $imageUrl = $data['image_url'] ?? null;
        $groupId = !empty($data['group_id']) ? (int)$data['group_id'] : null;
        $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;
        $createdAt = $data['created_at'] ?? date('Y-m-d H:i:s');

        $sql = "
            INSERT INTO feed_activity
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
                created_at = VALUES(created_at)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
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
        ]);
    }

    /**
     * Soft-hide an activity row (e.g. when content is deleted/deactivated/cancelled).
     * The row stays in the table but is excluded from feed queries.
     */
    public static function hideActivity(string $sourceType, int $sourceId): void
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "UPDATE feed_activity SET is_visible = 0 WHERE tenant_id = ? AND source_type = ? AND source_id = ?"
        );
        $stmt->execute([$tenantId, $sourceType, $sourceId]);
    }

    /**
     * Re-show a previously hidden activity row (e.g. when content is reactivated).
     */
    public static function showActivity(string $sourceType, int $sourceId): void
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "UPDATE feed_activity SET is_visible = 1 WHERE tenant_id = ? AND source_type = ? AND source_id = ?"
        );
        $stmt->execute([$tenantId, $sourceType, $sourceId]);
    }

    /**
     * Hard-delete an activity row (for permanent deletions only, e.g. GoalService::delete).
     */
    public static function removeActivity(string $sourceType, int $sourceId): void
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "DELETE FROM feed_activity WHERE tenant_id = ? AND source_type = ? AND source_id = ?"
        );
        $stmt->execute([$tenantId, $sourceType, $sourceId]);
    }
}
