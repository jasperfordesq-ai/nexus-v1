<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupModerationService
 *
 * Content moderation and safety tools for groups module.
 * Handles flagging, filtering, and moderating group content.
 */
class GroupModerationService
{
    // Moderation action types
    const ACTION_FLAG = 'flag';
    const ACTION_HIDE = 'hide';
    const ACTION_DELETE = 'delete';
    const ACTION_APPROVE = 'approve';
    const ACTION_WARN = 'warn';
    const ACTION_BAN = 'ban';

    // Content types
    const CONTENT_GROUP = 'group';
    const CONTENT_DISCUSSION = 'discussion';
    const CONTENT_POST = 'post';
    const CONTENT_FEEDBACK = 'feedback';

    // Flag reasons
    const REASON_SPAM = 'spam';
    const REASON_HARASSMENT = 'harassment';
    const REASON_INAPPROPRIATE = 'inappropriate';
    const REASON_MISINFORMATION = 'misinformation';
    const REASON_HATE_SPEECH = 'hate_speech';
    const REASON_VIOLENCE = 'violence';
    const REASON_OTHER = 'other';

    /**
     * Flag content for review
     *
     * @param string $contentType Content type constant
     * @param int $contentId Content ID
     * @param int $reportedBy User ID reporting
     * @param string $reason Reason constant
     * @param string $description Additional details
     * @return int|null Flag ID
     */
    public static function flagContent($contentType, $contentId, $reportedBy, $reason = self::REASON_OTHER, $description = '')
    {
        $tenantId = TenantContext::getId();
        self::ensureFlagsTableExists();

        try {
            Database::query(
                "INSERT INTO group_content_flags
                 (tenant_id, content_type, content_id, reported_by, reason, description, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')",
                [$tenantId, $contentType, $contentId, $reportedBy, $reason, $description]
            );

            return Database::lastInsertId();
        } catch (\Exception $e) {
            error_log("GroupModerationService: Failed to flag content - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Take moderation action on content
     *
     * @param int $flagId Flag ID
     * @param string $action Action constant
     * @param int $moderatorId Moderator user ID
     * @param string $moderatorNotes Notes from moderator
     * @return bool Success
     */
    public static function moderateContent($flagId, $action, $moderatorId, $moderatorNotes = '')
    {
        try {
            // Get flag details
            $flag = Database::query(
                "SELECT * FROM group_content_flags WHERE id = ?",
                [$flagId]
            )->fetch();

            if (!$flag) {
                return false;
            }

            // Update flag status
            Database::query(
                "UPDATE group_content_flags
                 SET status = 'resolved',
                     moderated_by = ?,
                     moderation_action = ?,
                     moderator_notes = ?,
                     resolved_at = NOW()
                 WHERE id = ?",
                [$moderatorId, $action, $moderatorNotes, $flagId]
            );

            // Take action based on type
            self::executeModerationAction($flag, $action, $moderatorId, $moderatorNotes);

            return true;
        } catch (\Exception $e) {
            error_log("GroupModerationService: Failed to moderate content - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute moderation action
     */
    private static function executeModerationAction($flag, $action, $moderatorId, $notes)
    {
        $contentType = $flag['content_type'];
        $contentId = $flag['content_id'];

        switch ($action) {
            case self::ACTION_DELETE:
                self::deleteContent($contentType, $contentId, $moderatorId, $notes);
                break;

            case self::ACTION_HIDE:
                self::hideContent($contentType, $contentId);
                break;

            case self::ACTION_BAN:
                self::banUser($flag['reported_by'], $moderatorId, $notes);
                break;

            case self::ACTION_WARN:
                self::warnUser($flag['reported_by'], $moderatorId, $notes);
                break;

            case self::ACTION_APPROVE:
                // Content is deemed appropriate, no action needed
                break;
        }
    }

    /**
     * Delete flagged content
     */
    private static function deleteContent($contentType, $contentId, $moderatorId, $reason)
    {
        switch ($contentType) {
            case self::CONTENT_DISCUSSION:
                Database::query("DELETE FROM group_discussions WHERE id = ?", [$contentId]);
                Database::query("DELETE FROM group_posts WHERE discussion_id = ?", [$contentId]);
                break;

            case self::CONTENT_POST:
                Database::query("DELETE FROM group_posts WHERE id = ?", [$contentId]);
                break;

            case self::CONTENT_FEEDBACK:
                Database::query("DELETE FROM group_feedback WHERE id = ?", [$contentId]);
                break;

            case self::CONTENT_GROUP:
                Database::query("DELETE FROM group_members WHERE group_id = ?", [$contentId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [$contentId]);
                GroupAuditService::logGroupDeleted($contentId, $moderatorId, $reason);
                break;
        }
    }

    /**
     * Hide content (soft delete)
     */
    private static function hideContent($contentType, $contentId)
    {
        switch ($contentType) {
            case self::CONTENT_DISCUSSION:
                Database::query(
                    "UPDATE group_discussions SET is_hidden = 1 WHERE id = ?",
                    [$contentId]
                );
                break;

            case self::CONTENT_POST:
                Database::query(
                    "UPDATE group_posts SET is_hidden = 1 WHERE id = ?",
                    [$contentId]
                );
                break;
        }
    }

    /**
     * Ban user from creating/joining groups
     */
    private static function banUser($userId, $moderatorId, $reason)
    {
        $tenantId = TenantContext::getId();
        self::ensureBansTableExists();

        try {
            Database::query(
                "INSERT INTO group_user_bans
                 (tenant_id, user_id, banned_by, reason, banned_until)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))",
                [$tenantId, $userId, $moderatorId, $reason]
            );

            // Remove from all groups
            Database::query(
                "DELETE FROM group_members WHERE user_id = ?",
                [$userId]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Warn user
     */
    private static function warnUser($userId, $moderatorId, $reason)
    {
        $tenantId = TenantContext::getId();
        self::ensureWarningsTableExists();

        try {
            Database::query(
                "INSERT INTO group_user_warnings
                 (tenant_id, user_id, warned_by, reason)
                 VALUES (?, ?, ?, ?)",
                [$tenantId, $userId, $moderatorId, $reason]
            );

            // Check if user has too many warnings
            $warningCount = Database::query(
                "SELECT COUNT(*) FROM group_user_warnings
                 WHERE tenant_id = ? AND user_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
                [$tenantId, $userId]
            )->fetchColumn();

            // Auto-ban after 3 warnings in 90 days
            if ($warningCount >= 3) {
                self::banUser($userId, $moderatorId, 'Excessive warnings');
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user is banned
     *
     * @param int $userId User ID
     * @return bool Is banned
     */
    public static function isUserBanned($userId)
    {
        $tenantId = TenantContext::getId();
        self::ensureBansTableExists();

        try {
            $result = Database::query(
                "SELECT 1 FROM group_user_bans
                 WHERE tenant_id = ? AND user_id = ?
                 AND (banned_until IS NULL OR banned_until > NOW())",
                [$tenantId, $userId]
            )->fetch();

            return (bool) $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get pending flags for review
     *
     * @param array $filters Optional filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Flags
     */
    public static function getPendingFlags($filters = [], $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT f.*,
                       u.first_name as reporter_first_name,
                       u.last_name as reporter_last_name,
                       CONCAT(u.first_name, ' ', u.last_name) as reporter_name
                FROM group_content_flags f
                LEFT JOIN users u ON f.reported_by = u.id
                WHERE f.tenant_id = ? AND f.status = 'pending'";

        $params = [$tenantId];

        if (!empty($filters['content_type'])) {
            $sql .= " AND f.content_type = ?";
            $params[] = $filters['content_type'];
        }

        if (!empty($filters['reason'])) {
            $sql .= " AND f.reason = ?";
            $params[] = $filters['reason'];
        }

        $sql .= " ORDER BY f.created_at ASC LIMIT $limit OFFSET $offset";

        try {
            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get moderation history
     *
     * @param array $filters Optional filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array History
     */
    public static function getModerationHistory($filters = [], $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT f.*,
                       u.first_name as reporter_first_name,
                       u.last_name as reporter_last_name,
                       m.first_name as moderator_first_name,
                       m.last_name as moderator_last_name
                FROM group_content_flags f
                LEFT JOIN users u ON f.reported_by = u.id
                LEFT JOIN users m ON f.moderated_by = m.id
                WHERE f.tenant_id = ? AND f.status = 'resolved'";

        $params = [$tenantId];

        if (!empty($filters['moderator_id'])) {
            $sql .= " AND f.moderated_by = ?";
            $params[] = $filters['moderator_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND f.moderation_action = ?";
            $params[] = $filters['action'];
        }

        $sql .= " ORDER BY f.resolved_at DESC LIMIT $limit OFFSET $offset";

        try {
            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check content against profanity filter
     *
     * @param string $content Content to check
     * @return array ['clean' => bool, 'matches' => array]
     */
    public static function checkProfanity($content)
    {
        if (!GroupConfigurationService::get(GroupConfigurationService::CONFIG_PROFANITY_FILTER_ENABLED)) {
            return ['clean' => true, 'matches' => []];
        }

        // Get banned words from policy
        $bannedWords = GroupPolicyRepository::getPolicy('banned_words', []);

        $contentLower = strtolower($content);
        $matches = [];

        foreach ($bannedWords as $word) {
            if (strpos($contentLower, strtolower($word)) !== false) {
                $matches[] = $word;
            }
        }

        return [
            'clean' => empty($matches),
            'matches' => $matches
        ];
    }

    /**
     * Auto-moderate content based on rules
     *
     * @param string $contentType Content type
     * @param string $content Content text
     * @return array ['approved' => bool, 'reason' => string]
     */
    public static function autoModerate($contentType, $content)
    {
        if (!GroupConfigurationService::get(GroupConfigurationService::CONFIG_CONTENT_FILTER_ENABLED)) {
            return ['approved' => true, 'reason' => ''];
        }

        // Check profanity
        $profanityCheck = self::checkProfanity($content);
        if (!$profanityCheck['clean']) {
            return [
                'approved' => false,
                'reason' => 'Contains inappropriate language: ' . implode(', ', $profanityCheck['matches'])
            ];
        }

        // Check for spam patterns
        if (self::containsSpamPatterns($content)) {
            return [
                'approved' => false,
                'reason' => 'Content appears to be spam'
            ];
        }

        return ['approved' => true, 'reason' => ''];
    }

    /**
     * Check for spam patterns
     *
     * @param string $content Content to check
     * @return bool Is spam
     */
    private static function containsSpamPatterns($content)
    {
        // Common spam indicators
        $spamPatterns = [
            '/\b(buy|cheap|discount|free|click here|limited time)\b/i',
            '/https?:\/\/[^\s]{50,}/', // Very long URLs
            '/(.)\1{10,}/', // Repeated characters
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get moderation statistics
     *
     * @param int $days Days to analyze
     * @return array Statistics
     */
    public static function getStatistics($days = 30)
    {
        $tenantId = TenantContext::getId();

        try {
            $stats = [
                'total_flags' => 0,
                'pending_flags' => 0,
                'resolved_flags' => 0,
                'actions_taken' => [],
                'top_reporters' => [],
                'flags_by_reason' => [],
            ];

            // Total and pending flags
            $stats['total_flags'] = (int) Database::query(
                "SELECT COUNT(*) FROM group_content_flags
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$tenantId, $days]
            )->fetchColumn();

            $stats['pending_flags'] = (int) Database::query(
                "SELECT COUNT(*) FROM group_content_flags
                 WHERE tenant_id = ? AND status = 'pending'",
                [$tenantId]
            )->fetchColumn();

            $stats['resolved_flags'] = (int) Database::query(
                "SELECT COUNT(*) FROM group_content_flags
                 WHERE tenant_id = ? AND status = 'resolved'
                 AND resolved_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$tenantId, $days]
            )->fetchColumn();

            // Actions taken
            $stats['actions_taken'] = Database::query(
                "SELECT moderation_action, COUNT(*) as count
                 FROM group_content_flags
                 WHERE tenant_id = ? AND status = 'resolved'
                 AND resolved_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY moderation_action",
                [$tenantId, $days]
            )->fetchAll();

            // Top reporters
            $stats['top_reporters'] = Database::query(
                "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name,
                        COUNT(f.id) as report_count
                 FROM group_content_flags f
                 JOIN users u ON f.reported_by = u.id
                 WHERE f.tenant_id = ?
                 AND f.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY u.id
                 ORDER BY report_count DESC
                 LIMIT 10",
                [$tenantId, $days]
            )->fetchAll();

            // Flags by reason
            $stats['flags_by_reason'] = Database::query(
                "SELECT reason, COUNT(*) as count
                 FROM group_content_flags
                 WHERE tenant_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY reason
                 ORDER BY count DESC",
                [$tenantId, $days]
            )->fetchAll();

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Ensure flags table exists
     */
    private static function ensureFlagsTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_content_flags LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_content_flags (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    content_type VARCHAR(50) NOT NULL,
                    content_id INT NOT NULL,
                    reported_by INT NOT NULL,
                    reason VARCHAR(50) NOT NULL,
                    description TEXT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    moderated_by INT NULL,
                    moderation_action VARCHAR(50) NULL,
                    moderator_notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    resolved_at TIMESTAMP NULL,
                    INDEX idx_tenant_status (tenant_id, status),
                    INDEX idx_content (content_type, content_id),
                    INDEX idx_reporter (tenant_id, reported_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Ensure bans table exists
     */
    private static function ensureBansTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_user_bans LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_user_bans (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT NOT NULL,
                    banned_by INT NOT NULL,
                    reason TEXT NULL,
                    banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    banned_until TIMESTAMP NULL,
                    UNIQUE KEY unique_tenant_user_ban (tenant_id, user_id),
                    INDEX idx_tenant_user (tenant_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Ensure warnings table exists
     */
    private static function ensureWarningsTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_user_warnings LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_user_warnings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT NOT NULL,
                    warned_by INT NOT NULL,
                    reason TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tenant_user (tenant_id, user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
