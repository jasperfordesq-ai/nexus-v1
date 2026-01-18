<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Middleware\SuperPanelAccess;

/**
 * SuperAdminAuditService
 *
 * Tracks all hierarchy changes made through the Super Admin Panel.
 * Provides audit trail for compliance and debugging.
 */
class SuperAdminAuditService
{
    /**
     * Log an action to the audit trail
     */
    public static function log(
        string $actionType,
        string $targetType,
        ?int $targetId = null,
        ?string $targetName = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): bool {
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return false;
        }

        // Get actor info
        $actor = Database::query(
            "SELECT id, first_name, last_name, email, tenant_id FROM users WHERE id = ?",
            [$access['user_id']]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$actor) {
            return false;
        }

        $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));

        try {
            Database::query("
                INSERT INTO super_admin_audit_log (
                    actor_user_id, actor_tenant_id, actor_name, actor_email,
                    action_type, target_type, target_id, target_name,
                    old_values, new_values, description,
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $actor['id'],
                $actor['tenant_id'],
                $actorName,
                $actor['email'],
                $actionType,
                $targetType,
                $targetId,
                $targetName,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("SuperAdminAuditService::log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit log entries with filters
     */
    public static function getLog(array $filters = []): array
    {
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        $sql = "SELECT * FROM super_admin_audit_log WHERE 1=1";
        $params = [];

        // Only master can see all logs; regional sees their subtree
        if ($access['level'] !== 'master') {
            $sql .= " AND actor_tenant_id IN (
                SELECT id FROM tenants WHERE path LIKE ?
            )";
            $params[] = $access['tenant_path'] . '%';
        }

        // Filter by action type
        if (!empty($filters['action_type'])) {
            $sql .= " AND action_type = ?";
            $params[] = $filters['action_type'];
        }

        // Filter by target type
        if (!empty($filters['target_type'])) {
            $sql .= " AND target_type = ?";
            $params[] = $filters['target_type'];
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

        // Search in description or target name
        if (!empty($filters['search'])) {
            $sql .= " AND (description LIKE ? OR target_name LIKE ? OR actor_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY created_at DESC";

        // Limit
        $limit = min((int)($filters['limit'] ?? 100), 500);
        $sql .= " LIMIT " . $limit;

        // Offset for pagination
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . (int)$filters['offset'];
        }

        $results = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($results as &$row) {
            $row['old_values'] = $row['old_values'] ? json_decode($row['old_values'], true) : null;
            $row['new_values'] = $row['new_values'] ? json_decode($row['new_values'], true) : null;
        }

        return $results;
    }

    /**
     * Get audit stats for dashboard
     */
    public static function getStats(int $days = 30): array
    {
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        $scopeCondition = "";
        $params = [date('Y-m-d H:i:s', strtotime("-{$days} days"))];

        if ($access['level'] !== 'master') {
            $scopeCondition = " AND actor_tenant_id IN (SELECT id FROM tenants WHERE path LIKE ?)";
            $params[] = $access['tenant_path'] . '%';
        }

        // Total actions
        $total = Database::query(
            "SELECT COUNT(*) FROM super_admin_audit_log WHERE created_at >= ? {$scopeCondition}",
            $params
        )->fetchColumn();

        // Actions by type
        $byType = Database::query("
            SELECT action_type, COUNT(*) as count
            FROM super_admin_audit_log
            WHERE created_at >= ? {$scopeCondition}
            GROUP BY action_type
            ORDER BY count DESC
        ", $params)->fetchAll(\PDO::FETCH_ASSOC);

        // Recent actors
        $topActors = Database::query("
            SELECT actor_user_id, actor_name, actor_email, COUNT(*) as action_count
            FROM super_admin_audit_log
            WHERE created_at >= ? {$scopeCondition}
            GROUP BY actor_user_id, actor_name, actor_email
            ORDER BY action_count DESC
            LIMIT 10
        ", $params)->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total_actions' => (int)$total,
            'by_type' => $byType,
            'top_actors' => $topActors,
            'period_days' => $days
        ];
    }

    /**
     * Helper: Get human-readable action description
     */
    public static function getActionLabel(string $actionType): string
    {
        $labels = [
            'tenant_created' => 'Tenant Created',
            'tenant_updated' => 'Tenant Updated',
            'tenant_deleted' => 'Tenant Deleted',
            'tenant_moved' => 'Tenant Moved',
            'hub_toggled' => 'Hub Toggled',
            'super_admin_granted' => 'Super Admin Granted',
            'super_admin_revoked' => 'Super Admin Revoked',
            'user_created' => 'User Created',
            'user_updated' => 'User Updated',
            'user_moved' => 'User Moved',
            'bulk_users_moved' => 'Bulk Users Moved',
            'bulk_tenants_updated' => 'Bulk Tenants Updated'
        ];

        return $labels[$actionType] ?? $actionType;
    }

    /**
     * Helper: Get action icon class
     */
    public static function getActionIcon(string $actionType): string
    {
        $icons = [
            'tenant_created' => 'fa-plus-circle text-success',
            'tenant_updated' => 'fa-edit text-info',
            'tenant_deleted' => 'fa-trash text-danger',
            'tenant_moved' => 'fa-exchange-alt text-warning',
            'hub_toggled' => 'fa-network-wired text-purple',
            'super_admin_granted' => 'fa-crown text-success',
            'super_admin_revoked' => 'fa-user-minus text-danger',
            'user_created' => 'fa-user-plus text-success',
            'user_updated' => 'fa-user-edit text-info',
            'user_moved' => 'fa-user-friends text-warning',
            'bulk_users_moved' => 'fa-users text-warning',
            'bulk_tenants_updated' => 'fa-sitemap text-info'
        ];

        return $icons[$actionType] ?? 'fa-circle';
    }
}
