<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ContentModerationService
 *
 * Platform-wide content moderation queue for all user-generated content:
 * posts, listings, events, comments, and groups.
 *
 * Supports configurable per-tenant moderation settings and auto-filtering
 * for profanity/spam detection.
 *
 * Content lifecycle with moderation:
 * 1. User creates content
 * 2. If moderation is enabled for that content type, content is added to queue
 *    with status='pending' and the content itself is created with a hidden/draft state
 * 3. Admin reviews and approves/rejects via the moderation queue
 * 4. Approved content becomes visible; rejected content stays hidden with a reason
 *
 * All methods are tenant-scoped.
 */
class ContentModerationService
{
    /**
     * Content types that can be moderated
     */
    public const CONTENT_TYPES = ['post', 'listing', 'event', 'comment', 'group'];

    /**
     * Moderation statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FLAGGED = 'flagged';

    /**
     * Check if moderation is enabled for a content type
     *
     * @param int $tenantId
     * @param string $contentType
     * @return bool
     */
    public static function isEnabled(int $tenantId, string $contentType): bool
    {
        // Check global moderation toggle
        try {
            $globalEnabled = TenantSettingsService::get($tenantId, 'moderation.enabled');
            if (!$globalEnabled) {
                return false;
            }

            $typeEnabled = TenantSettingsService::get($tenantId, "moderation.require_{$contentType}");
            return (bool) $typeEnabled;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Submit content for moderation
     *
     * Called when a user creates content and moderation is enabled.
     * Returns the queue item ID if moderation is required, null otherwise.
     *
     * @param int $tenantId
     * @param string $contentType 'post', 'listing', 'event', 'comment', 'group'
     * @param int $contentId ID of the content item
     * @param int $authorId User who created the content
     * @param string|null $title Optional title/summary for quick admin review
     * @param string|null $textContent Optional content text for auto-filtering
     * @return int|null Queue item ID if moderation is required, null if not needed
     */
    public static function submit(
        int $tenantId,
        string $contentType,
        int $contentId,
        int $authorId,
        ?string $title = null,
        ?string $textContent = null
    ): ?int {
        self::ensureTableExists();

        if (!self::isEnabled($tenantId, $contentType)) {
            return null;
        }

        // Auto-filter check
        $autoFlagged = false;
        $flagReason = null;

        if ($textContent && self::isAutoFilterEnabled($tenantId)) {
            $filterResult = self::autoFilter($textContent);
            if (!$filterResult['clean']) {
                $autoFlagged = true;
                $flagReason = $filterResult['reason'];
            }
        }

        $status = $autoFlagged ? self::STATUS_FLAGGED : self::STATUS_PENDING;

        try {
            Database::query(
                "INSERT INTO content_moderation_queue
                    (tenant_id, content_type, content_id, author_id, title, status, auto_flagged, flag_reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $contentType, $contentId, $authorId, $title, $status, $autoFlagged ? 1 : 0, $flagReason]
            );

            return (int) Database::lastInsertId();
        } catch (\Exception $e) {
            error_log("ContentModerationService::submit failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get moderation queue with filtering
     *
     * @param int $tenantId
     * @param array $filters ['status', 'content_type', 'search']
     * @param int $limit
     * @param int $offset
     * @return array ['items' => [...], 'total' => int]
     */
    public static function getQueue(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        self::ensureTableExists();

        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);

        $conditions = ['q.tenant_id = ?'];
        $params = [$tenantId];

        if (!empty($filters['status']) && in_array($filters['status'], self::CONTENT_TYPES, true) === false) {
            if (in_array($filters['status'], [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_FLAGGED], true)) {
                $conditions[] = 'q.status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['content_type']) && in_array($filters['content_type'], self::CONTENT_TYPES, true)) {
            $conditions[] = 'q.content_type = ?';
            $params[] = $filters['content_type'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(q.title LIKE ? OR author.first_name LIKE ? OR author.last_name LIKE ?)';
            $searchPattern = '%' . $filters['search'] . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = implode(' AND ', $conditions);

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) FROM content_moderation_queue q
             LEFT JOIN users author ON q.author_id = author.id
             WHERE {$where}",
            $params
        )->fetchColumn();

        // Fetch items
        $queryParams = array_merge($params, [$limit, $offset]);
        $stmt = Database::query(
            "SELECT q.*,
                    CONCAT(author.first_name, ' ', author.last_name) as author_name,
                    author.email as author_email,
                    author.avatar_url as author_avatar,
                    CONCAT(reviewer.first_name, ' ', reviewer.last_name) as reviewer_name
             FROM content_moderation_queue q
             LEFT JOIN users author ON q.author_id = author.id
             LEFT JOIN users reviewer ON q.reviewer_id = reviewer.id
             WHERE {$where}
             ORDER BY
                CASE q.status
                    WHEN 'flagged' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'rejected' THEN 3
                    WHEN 'approved' THEN 4
                END,
                q.created_at ASC
             LIMIT ? OFFSET ?",
            $queryParams
        );

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = [
                'id' => (int) $row['id'],
                'content_type' => $row['content_type'],
                'content_id' => (int) $row['content_id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'author' => [
                    'id' => (int) $row['author_id'],
                    'name' => $row['author_name'],
                    'email' => $row['author_email'],
                    'avatar' => $row['author_avatar'],
                ],
                'auto_flagged' => (bool) $row['auto_flagged'],
                'flag_reason' => $row['flag_reason'],
                'reviewer' => $row['reviewer_id'] ? [
                    'id' => (int) $row['reviewer_id'],
                    'name' => $row['reviewer_name'],
                ] : null,
                'reviewed_at' => $row['reviewed_at'],
                'rejection_reason' => $row['rejection_reason'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Review (approve/reject) a moderation queue item
     *
     * @param int $queueId
     * @param int $tenantId
     * @param int $reviewerId Admin user ID
     * @param string $decision 'approved' or 'rejected'
     * @param string|null $rejectionReason Required if rejecting
     * @return array ['success' => bool, 'message' => string]
     */
    public static function review(
        int $queueId,
        int $tenantId,
        int $reviewerId,
        string $decision,
        ?string $rejectionReason = null
    ): array {
        self::ensureTableExists();

        if (!in_array($decision, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return ['success' => false, 'message' => 'Invalid decision. Must be approved or rejected.'];
        }

        // Get queue item
        $item = Database::query(
            "SELECT * FROM content_moderation_queue WHERE id = ? AND tenant_id = ?",
            [$queueId, $tenantId]
        )->fetch();

        if (!$item) {
            return ['success' => false, 'message' => 'Moderation queue item not found.'];
        }

        if ($item['status'] === self::STATUS_APPROVED || $item['status'] === self::STATUS_REJECTED) {
            return ['success' => false, 'message' => 'This item has already been reviewed.'];
        }

        if ($decision === self::STATUS_REJECTED && empty($rejectionReason)) {
            return ['success' => false, 'message' => 'Rejection reason is required.'];
        }

        // Update queue item
        Database::query(
            "UPDATE content_moderation_queue
             SET status = ?, reviewer_id = ?, reviewed_at = NOW(), rejection_reason = ?
             WHERE id = ? AND tenant_id = ?",
            [$decision, $reviewerId, $rejectionReason, $queueId, $tenantId]
        );

        // Apply decision to the actual content
        self::applyDecision($item, $decision, $tenantId);

        return [
            'success' => true,
            'message' => "Content has been {$decision}.",
            'content_type' => $item['content_type'],
            'content_id' => (int) $item['content_id'],
        ];
    }

    /**
     * Get moderation queue statistics
     *
     * @param int $tenantId
     * @return array
     */
    public static function getStats(int $tenantId): array
    {
        self::ensureTableExists();

        $stats = Database::query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN auto_flagged = 1 THEN 1 ELSE 0 END) as auto_flagged_total
             FROM content_moderation_queue
             WHERE tenant_id = ?",
            [$tenantId]
        )->fetch();

        // By content type
        $byType = Database::query(
            "SELECT content_type, status, COUNT(*) as count
             FROM content_moderation_queue
             WHERE tenant_id = ?
             GROUP BY content_type, status
             ORDER BY content_type, status",
            [$tenantId]
        )->fetchAll();

        $typeBreakdown = [];
        foreach ($byType as $row) {
            $type = $row['content_type'];
            if (!isset($typeBreakdown[$type])) {
                $typeBreakdown[$type] = ['pending' => 0, 'flagged' => 0, 'approved' => 0, 'rejected' => 0];
            }
            $typeBreakdown[$type][$row['status']] = (int) $row['count'];
        }

        // Get moderation settings
        $settings = self::getModerationSettings($tenantId);

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'flagged' => (int) ($stats['flagged'] ?? 0),
            'approved' => (int) ($stats['approved'] ?? 0),
            'rejected' => (int) ($stats['rejected'] ?? 0),
            'auto_flagged_total' => (int) ($stats['auto_flagged_total'] ?? 0),
            'awaiting_review' => (int) ($stats['pending'] ?? 0) + (int) ($stats['flagged'] ?? 0),
            'by_type' => $typeBreakdown,
            'settings' => $settings,
        ];
    }

    /**
     * Get moderation settings for a tenant
     *
     * @param int $tenantId
     * @return array
     */
    public static function getModerationSettings(int $tenantId): array
    {
        $settings = [
            'enabled' => false,
            'require_post' => false,
            'require_listing' => false,
            'require_event' => false,
            'require_comment' => false,
            'auto_filter' => false,
        ];

        try {
            foreach ($settings as $key => $default) {
                $value = TenantSettingsService::get($tenantId, "moderation.{$key}");
                $settings[$key] = (bool) $value;
            }
        } catch (\Exception $e) {
            // TenantSettingsService not available, use defaults
        }

        return $settings;
    }

    /**
     * Update moderation settings for a tenant
     *
     * @param int $tenantId
     * @param array $settings
     * @return bool
     */
    public static function updateSettings(int $tenantId, array $settings): bool
    {
        $allowedKeys = ['enabled', 'require_post', 'require_listing', 'require_event', 'require_comment', 'auto_filter'];

        try {
            foreach ($allowedKeys as $key) {
                if (isset($settings[$key])) {
                    TenantSettingsService::set($tenantId, "moderation.{$key}", $settings[$key] ? '1' : '0');
                }
            }
            return true;
        } catch (\Exception $e) {
            error_log("ContentModerationService::updateSettings failed: " . $e->getMessage());
            return false;
        }
    }

    // ============================================
    // INTERNAL METHODS
    // ============================================

    /**
     * Apply moderation decision to the actual content
     */
    private static function applyDecision(array $item, string $decision, int $tenantId): void
    {
        $contentType = $item['content_type'];
        $contentId = (int) $item['content_id'];

        try {
            if ($decision === self::STATUS_APPROVED) {
                // Make content visible
                switch ($contentType) {
                    case 'post':
                        Database::query(
                            "UPDATE feed_posts SET is_hidden = 0 WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                    case 'listing':
                        Database::query(
                            "UPDATE listings SET status = 'active' WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                    case 'event':
                        Database::query(
                            "UPDATE events SET status = 'published' WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                    case 'comment':
                        Database::query(
                            "UPDATE comments SET is_hidden = 0 WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                }
            } elseif ($decision === self::STATUS_REJECTED) {
                // Keep content hidden/remove
                switch ($contentType) {
                    case 'post':
                        Database::query(
                            "UPDATE feed_posts SET is_hidden = 1 WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                    case 'listing':
                        Database::query(
                            "UPDATE listings SET status = 'rejected' WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                    case 'event':
                        Database::query(
                            "UPDATE events SET status = 'cancelled' WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                    case 'comment':
                        Database::query(
                            "UPDATE comments SET is_hidden = 1 WHERE id = ? AND tenant_id = ?",
                            [$contentId, $tenantId]
                        );
                        break;
                }
            }
        } catch (\Exception $e) {
            error_log("ContentModerationService::applyDecision failed for {$contentType} #{$contentId}: " . $e->getMessage());
        }
    }

    /**
     * Check if auto-filter is enabled for a tenant
     */
    private static function isAutoFilterEnabled(int $tenantId): bool
    {
        try {
            return (bool) TenantSettingsService::get($tenantId, 'moderation.auto_filter');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run auto-filter on content text
     *
     * @param string $text
     * @return array ['clean' => bool, 'reason' => string|null]
     */
    private static function autoFilter(string $text): array
    {
        // Spam patterns
        $spamPatterns = [
            '/\b(buy now|click here|limited offer|free money|act now)\b/i',
            '/https?:\/\/[^\s]{80,}/',   // Excessively long URLs
            '/(.)\1{15,}/',              // 15+ repeated characters
            '/[A-Z\s]{50,}/',            // 50+ chars of ALL CAPS
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return ['clean' => false, 'reason' => 'Content matches spam patterns'];
            }
        }

        return ['clean' => true, 'reason' => null];
    }

    /**
     * Ensure the content_moderation_queue table exists
     */
    private static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            Database::query("SELECT 1 FROM content_moderation_queue LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS `content_moderation_queue` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` INT NOT NULL,
                    `content_type` ENUM('post','listing','event','comment','group') NOT NULL,
                    `content_id` INT NOT NULL,
                    `author_id` INT NOT NULL,
                    `title` VARCHAR(255) NULL,
                    `status` ENUM('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
                    `reviewer_id` INT NULL,
                    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
                    `rejection_reason` TEXT NULL,
                    `auto_flagged` TINYINT(1) NOT NULL DEFAULT 0,
                    `flag_reason` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_tenant_status` (`tenant_id`, `status`),
                    INDEX `idx_tenant_type_status` (`tenant_id`, `content_type`, `status`),
                    INDEX `idx_content` (`content_type`, `content_id`),
                    INDEX `idx_author` (`tenant_id`, `author_id`),
                    INDEX `idx_reviewer` (`reviewer_id`),
                    INDEX `idx_created` (`tenant_id`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
