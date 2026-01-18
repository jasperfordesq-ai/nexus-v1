<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederationAuditService
 *
 * Comprehensive audit logging for all federation operations.
 * Every cross-tenant interaction is logged for:
 * - Compliance and accountability
 * - Security monitoring
 * - Debugging and troubleshooting
 * - Usage analytics
 * - Emergency incident investigation
 *
 * Follows the SuperAdminAuditService pattern.
 */
class FederationAuditService
{
    // Log levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_CRITICAL = 'critical';

    // Action categories
    const CATEGORY_SYSTEM = 'system';          // Emergency lockdown, feature toggles
    const CATEGORY_TENANT = 'tenant';          // Tenant federation settings
    const CATEGORY_PARTNERSHIP = 'partnership'; // Partnership requests/approvals
    const CATEGORY_PROFILE = 'profile';        // Profile visibility
    const CATEGORY_MESSAGING = 'messaging';    // Cross-tenant messages
    const CATEGORY_TRANSACTION = 'transaction'; // Cross-tenant exchanges
    const CATEGORY_LISTING = 'listing';        // Listing visibility
    const CATEGORY_EVENT = 'event';            // Event sharing
    const CATEGORY_GROUP = 'group';            // Group federation
    const CATEGORY_SEARCH = 'search';          // Federated searches

    /**
     * Log a federation action
     *
     * @param string $actionType Type of action (e.g., 'emergency_lockdown_triggered')
     * @param int|null $sourceTenantId Tenant initiating the action (null for system actions)
     * @param int|null $targetTenantId Target tenant if applicable
     * @param int|null $actorUserId User performing the action (null for system)
     * @param array $data Additional context data
     * @param string $level Log level (info, warning, critical)
     * @return bool Success
     */
    public static function log(
        string $actionType,
        ?int $sourceTenantId = null,
        ?int $targetTenantId = null,
        ?int $actorUserId = null,
        array $data = [],
        string $level = self::LEVEL_INFO
    ): bool {
        self::ensureTableExists();

        // Auto-determine source tenant if not provided
        if ($sourceTenantId === null) {
            try {
                $sourceTenantId = TenantContext::getId();
            } catch (\Exception $e) {
                // System-level action with no tenant context
                $sourceTenantId = null;
            }
        }

        // Get actor info if available
        $actorName = null;
        $actorEmail = null;
        if ($actorUserId) {
            try {
                $actor = Database::query(
                    "SELECT first_name, last_name, email FROM users WHERE id = ?",
                    [$actorUserId]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($actor) {
                    $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
                    $actorEmail = $actor['email'] ?? null;
                }
            } catch (\Exception $e) {
                // Actor lookup failed - continue without actor details
            }
        }

        // Determine category from action type
        $category = self::getCategoryFromAction($actionType);

        try {
            Database::query("
                INSERT INTO federation_audit_log (
                    action_type, category, level,
                    source_tenant_id, target_tenant_id,
                    actor_user_id, actor_name, actor_email,
                    data, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $actionType,
                $category,
                $level,
                $sourceTenantId,
                $targetTenantId,
                $actorUserId,
                $actorName,
                $actorEmail,
                !empty($data) ? json_encode($data) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null
            ]);

            // For critical events, also log to error_log for immediate visibility
            if ($level === self::LEVEL_CRITICAL) {
                error_log("[FEDERATION CRITICAL] {$actionType}: " . json_encode([
                    'source_tenant' => $sourceTenantId,
                    'target_tenant' => $targetTenantId,
                    'actor' => $actorUserId,
                    'data' => $data
                ]));
            }

            return true;

        } catch (\Exception $e) {
            error_log("FederationAuditService::log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a federated search query
     */
    public static function logSearch(
        string $searchType,
        array $filters,
        int $resultsCount,
        ?int $actorUserId = null
    ): bool {
        return self::log(
            'federated_search',
            null,
            null,
            $actorUserId,
            [
                'search_type' => $searchType,
                'filters' => $filters,
                'results_count' => $resultsCount
            ],
            self::LEVEL_DEBUG
        );
    }

    /**
     * Log a cross-tenant profile view
     */
    public static function logProfileView(
        int $viewerUserId,
        int $viewerTenantId,
        int $viewedUserId,
        int $viewedTenantId
    ): bool {
        return self::log(
            'cross_tenant_profile_view',
            $viewerTenantId,
            $viewedTenantId,
            $viewerUserId,
            [
                'viewed_user_id' => $viewedUserId
            ],
            self::LEVEL_DEBUG
        );
    }

    /**
     * Log a cross-tenant message
     */
    public static function logMessage(
        int $senderUserId,
        int $senderTenantId,
        int $recipientUserId,
        int $recipientTenantId,
        ?int $messageId = null
    ): bool {
        return self::log(
            'cross_tenant_message',
            $senderTenantId,
            $recipientTenantId,
            $senderUserId,
            [
                'recipient_user_id' => $recipientUserId,
                'message_id' => $messageId
            ],
            self::LEVEL_INFO
        );
    }

    /**
     * Log a cross-tenant transaction
     */
    public static function logTransaction(
        int $initiatorUserId,
        int $initiatorTenantId,
        int $counterpartyUserId,
        int $counterpartyTenantId,
        int $transactionId,
        string $transactionType,
        float $amount
    ): bool {
        return self::log(
            'cross_tenant_transaction',
            $initiatorTenantId,
            $counterpartyTenantId,
            $initiatorUserId,
            [
                'counterparty_user_id' => $counterpartyUserId,
                'transaction_id' => $transactionId,
                'transaction_type' => $transactionType,
                'amount' => $amount
            ],
            self::LEVEL_INFO
        );
    }

    /**
     * Log a partnership status change
     */
    public static function logPartnershipChange(
        int $tenantId,
        int $partnerTenantId,
        string $newStatus,
        ?int $actorUserId = null,
        ?string $reason = null
    ): bool {
        return self::log(
            'partnership_status_changed',
            $tenantId,
            $partnerTenantId,
            $actorUserId,
            [
                'new_status' => $newStatus,
                'reason' => $reason
            ],
            self::LEVEL_INFO
        );
    }

    /**
     * Get audit log entries with filters
     */
    public static function getLog(array $filters = []): array
    {
        self::ensureTableExists();

        $sql = "SELECT * FROM federation_audit_log WHERE 1=1";
        $params = [];

        // Filter by category
        if (!empty($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }

        // Filter by level
        if (!empty($filters['level'])) {
            $sql .= " AND level = ?";
            $params[] = $filters['level'];
        }

        // Filter by action type
        if (!empty($filters['action_type'])) {
            $sql .= " AND action_type = ?";
            $params[] = $filters['action_type'];
        }

        // Filter by source tenant
        if (!empty($filters['source_tenant_id'])) {
            $sql .= " AND source_tenant_id = ?";
            $params[] = $filters['source_tenant_id'];
        }

        // Filter by target tenant
        if (!empty($filters['target_tenant_id'])) {
            $sql .= " AND target_tenant_id = ?";
            $params[] = $filters['target_tenant_id'];
        }

        // Filter by actor
        if (!empty($filters['actor_user_id'])) {
            $sql .= " AND actor_user_id = ?";
            $params[] = $filters['actor_user_id'];
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Search in data
        if (!empty($filters['search'])) {
            $sql .= " AND (action_type LIKE ? OR data LIKE ? OR actor_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY created_at DESC";

        // Limit
        $limit = min((int)($filters['limit'] ?? 100), 1000);
        $sql .= " LIMIT " . $limit;

        // Offset for pagination
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . (int)$filters['offset'];
        }

        try {
            $results = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

            // Decode JSON data field
            foreach ($results as &$row) {
                $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
            }

            return $results;

        } catch (\Exception $e) {
            error_log("FederationAuditService::getLog error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get audit statistics
     */
    public static function getStats(int $days = 30): array
    {
        self::ensureTableExists();

        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        try {
            // Total actions
            $total = Database::query(
                "SELECT COUNT(*) FROM federation_audit_log WHERE created_at >= ?",
                [$since]
            )->fetchColumn();

            // By category
            $byCategory = Database::query("
                SELECT category, COUNT(*) as count
                FROM federation_audit_log
                WHERE created_at >= ?
                GROUP BY category
                ORDER BY count DESC
            ", [$since])->fetchAll(\PDO::FETCH_ASSOC);

            // By level
            $byLevel = Database::query("
                SELECT level, COUNT(*) as count
                FROM federation_audit_log
                WHERE created_at >= ?
                GROUP BY level
                ORDER BY count DESC
            ", [$since])->fetchAll(\PDO::FETCH_ASSOC);

            // Critical events
            $criticalCount = Database::query(
                "SELECT COUNT(*) FROM federation_audit_log WHERE created_at >= ? AND level = 'critical'",
                [$since]
            )->fetchColumn();

            // Top action types
            $topActions = Database::query("
                SELECT action_type, COUNT(*) as count
                FROM federation_audit_log
                WHERE created_at >= ?
                GROUP BY action_type
                ORDER BY count DESC
                LIMIT 10
            ", [$since])->fetchAll(\PDO::FETCH_ASSOC);

            // Most active tenant pairs
            $activePairs = Database::query("
                SELECT source_tenant_id, target_tenant_id, COUNT(*) as interactions
                FROM federation_audit_log
                WHERE created_at >= ?
                    AND source_tenant_id IS NOT NULL
                    AND target_tenant_id IS NOT NULL
                GROUP BY source_tenant_id, target_tenant_id
                ORDER BY interactions DESC
                LIMIT 10
            ", [$since])->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'total_actions' => (int)$total,
                'by_category' => $byCategory,
                'by_level' => $byLevel,
                'critical_count' => (int)$criticalCount,
                'top_actions' => $topActions,
                'active_pairs' => $activePairs,
                'period_days' => $days
            ];

        } catch (\Exception $e) {
            error_log("FederationAuditService::getStats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent critical events (for dashboard alerts)
     */
    public static function getRecentCritical(int $limit = 10): array
    {
        return self::getLog([
            'level' => self::LEVEL_CRITICAL,
            'limit' => $limit
        ]);
    }

    /**
     * Purge old audit logs (retention policy)
     * Should be called by a scheduled job
     */
    public static function purgeOld(int $retentionDays = 365): int
    {
        self::ensureTableExists();

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        try {
            // Keep critical events longer
            $criticalRetention = $retentionDays * 2;
            $criticalCutoff = date('Y-m-d H:i:s', strtotime("-{$criticalRetention} days"));

            $stmt = Database::query("
                DELETE FROM federation_audit_log
                WHERE (level != 'critical' AND created_at < ?)
                   OR (level = 'critical' AND created_at < ?)
            ", [$cutoffDate, $criticalCutoff]);

            $deleted = $stmt->rowCount();

            // Log the purge itself
            self::log(
                'audit_log_purged',
                null,
                null,
                null,
                [
                    'deleted_count' => $deleted,
                    'retention_days' => $retentionDays,
                    'cutoff_date' => $cutoffDate
                ],
                self::LEVEL_INFO
            );

            return $deleted;

        } catch (\Exception $e) {
            error_log("FederationAuditService::purgeOld error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Determine category from action type
     */
    private static function getCategoryFromAction(string $actionType): string
    {
        $categoryMap = [
            // System actions
            'emergency_lockdown_triggered' => self::CATEGORY_SYSTEM,
            'emergency_lockdown_lifted' => self::CATEGORY_SYSTEM,
            'system_feature_changed' => self::CATEGORY_SYSTEM,
            'whitelist_mode_changed' => self::CATEGORY_SYSTEM,
            'audit_log_purged' => self::CATEGORY_SYSTEM,

            // Tenant actions
            'tenant_feature_changed' => self::CATEGORY_TENANT,
            'tenant_whitelisted' => self::CATEGORY_TENANT,
            'tenant_removed_from_whitelist' => self::CATEGORY_TENANT,
            'tenant_federation_enabled' => self::CATEGORY_TENANT,
            'tenant_federation_disabled' => self::CATEGORY_TENANT,

            // Partnership actions
            'partnership_requested' => self::CATEGORY_PARTNERSHIP,
            'partnership_approved' => self::CATEGORY_PARTNERSHIP,
            'partnership_rejected' => self::CATEGORY_PARTNERSHIP,
            'partnership_revoked' => self::CATEGORY_PARTNERSHIP,
            'partnership_status_changed' => self::CATEGORY_PARTNERSHIP,

            // Profile actions
            'cross_tenant_profile_view' => self::CATEGORY_PROFILE,
            'profile_visibility_changed' => self::CATEGORY_PROFILE,

            // Messaging actions
            'cross_tenant_message' => self::CATEGORY_MESSAGING,
            'cross_tenant_message_blocked' => self::CATEGORY_MESSAGING,

            // Transaction actions
            'cross_tenant_transaction' => self::CATEGORY_TRANSACTION,
            'cross_tenant_transaction_failed' => self::CATEGORY_TRANSACTION,

            // Listing actions
            'listing_federated' => self::CATEGORY_LISTING,
            'listing_unfederated' => self::CATEGORY_LISTING,

            // Event actions
            'event_federated' => self::CATEGORY_EVENT,
            'event_unfederated' => self::CATEGORY_EVENT,
            'cross_tenant_event_join' => self::CATEGORY_EVENT,

            // Group actions
            'group_federation_enabled' => self::CATEGORY_GROUP,
            'cross_tenant_group_join' => self::CATEGORY_GROUP,

            // Search actions
            'federated_search' => self::CATEGORY_SEARCH,
        ];

        return $categoryMap[$actionType] ?? self::CATEGORY_SYSTEM;
    }

    /**
     * Get human-readable action label
     */
    public static function getActionLabel(string $actionType): string
    {
        $labels = [
            'emergency_lockdown_triggered' => 'Emergency Lockdown Triggered',
            'emergency_lockdown_lifted' => 'Emergency Lockdown Lifted',
            'system_feature_changed' => 'System Feature Changed',
            'tenant_feature_changed' => 'Tenant Feature Changed',
            'tenant_whitelisted' => 'Tenant Whitelisted',
            'tenant_removed_from_whitelist' => 'Tenant Removed from Whitelist',
            'partnership_requested' => 'Partnership Requested',
            'partnership_approved' => 'Partnership Approved',
            'partnership_rejected' => 'Partnership Rejected',
            'partnership_status_changed' => 'Partnership Status Changed',
            'cross_tenant_profile_view' => 'Cross-Tenant Profile View',
            'cross_tenant_message' => 'Cross-Tenant Message',
            'cross_tenant_transaction' => 'Cross-Tenant Transaction',
            'federated_search' => 'Federated Search',
            'audit_log_purged' => 'Audit Log Purged',
        ];

        return $labels[$actionType] ?? ucwords(str_replace('_', ' ', $actionType));
    }

    /**
     * Get icon class for action type
     */
    public static function getActionIcon(string $actionType): string
    {
        $icons = [
            'emergency_lockdown_triggered' => 'fa-exclamation-triangle text-danger',
            'emergency_lockdown_lifted' => 'fa-check-circle text-success',
            'tenant_whitelisted' => 'fa-user-check text-success',
            'tenant_removed_from_whitelist' => 'fa-user-times text-warning',
            'partnership_requested' => 'fa-handshake text-info',
            'partnership_approved' => 'fa-check text-success',
            'partnership_rejected' => 'fa-times text-danger',
            'cross_tenant_profile_view' => 'fa-eye text-muted',
            'cross_tenant_message' => 'fa-envelope text-primary',
            'cross_tenant_transaction' => 'fa-exchange-alt text-success',
            'federated_search' => 'fa-search text-info',
        ];

        return $icons[$actionType] ?? 'fa-circle';
    }

    /**
     * Get level badge class
     */
    public static function getLevelBadge(string $level): string
    {
        $badges = [
            self::LEVEL_DEBUG => 'badge-secondary',
            self::LEVEL_INFO => 'badge-info',
            self::LEVEL_WARNING => 'badge-warning',
            self::LEVEL_CRITICAL => 'badge-danger',
        ];

        return $badges[$level] ?? 'badge-secondary';
    }

    /**
     * Ensure audit log table exists
     */
    private static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM federation_audit_log LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS federation_audit_log (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                    -- Action info
                    action_type VARCHAR(100) NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    level ENUM('debug', 'info', 'warning', 'critical') NOT NULL DEFAULT 'info',

                    -- Tenant context
                    source_tenant_id INT UNSIGNED NULL
                        COMMENT 'Tenant initiating the action',
                    target_tenant_id INT UNSIGNED NULL
                        COMMENT 'Target tenant if applicable',

                    -- Actor info
                    actor_user_id INT UNSIGNED NULL,
                    actor_name VARCHAR(200) NULL,
                    actor_email VARCHAR(255) NULL,

                    -- Additional context
                    data JSON NULL
                        COMMENT 'Additional context data in JSON format',

                    -- Request metadata
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(500) NULL,

                    -- Timestamp
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                    -- Indexes for efficient querying
                    INDEX idx_action_type (action_type),
                    INDEX idx_category (category),
                    INDEX idx_level (level),
                    INDEX idx_source_tenant (source_tenant_id),
                    INDEX idx_target_tenant (target_tenant_id),
                    INDEX idx_actor (actor_user_id),
                    INDEX idx_created_at (created_at),
                    INDEX idx_level_created (level, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
