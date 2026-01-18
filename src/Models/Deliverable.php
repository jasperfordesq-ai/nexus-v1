<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use PDO;
use Exception;

/**
 * Deliverable Model
 *
 * Manages project deliverables with tracking, status management, and collaboration features.
 * Follows the static method pattern established in the NEXUS codebase.
 */
class Deliverable
{
    /**
     * Create a new deliverable
     *
     * @param int $ownerId User ID of deliverable owner
     * @param string $title Deliverable title
     * @param string|null $description Detailed description
     * @param array $options Additional options (category, priority, assigned_to, etc.)
     * @return array|false Created deliverable or false on failure
     */
    public static function create($ownerId, $title, $description = null, $options = [])
    {
        $tenantId = TenantContext::getId();

        $defaults = [
            'category' => 'general',
            'priority' => 'medium',
            'assigned_to' => null,
            'assigned_group_id' => null,
            'start_date' => null,
            'due_date' => null,
            'status' => 'draft',
            'progress_percentage' => 0.00,
            'estimated_hours' => null,
            'parent_deliverable_id' => null,
            'tags' => null,
            'delivery_confidence' => 'medium',
            'risk_level' => 'low',
            'risk_notes' => null,
        ];

        $data = array_merge($defaults, $options);

        // Convert arrays to JSON
        $tags = isset($data['tags']) && is_array($data['tags']) ? json_encode($data['tags']) : null;

        $sql = "INSERT INTO deliverables (
            tenant_id, owner_id, title, description, category, priority,
            assigned_to, assigned_group_id, start_date, due_date, status,
            progress_percentage, estimated_hours, parent_deliverable_id,
            tags, delivery_confidence, risk_level, risk_notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $tenantId,
            $ownerId,
            $title,
            $description,
            $data['category'],
            $data['priority'],
            $data['assigned_to'],
            $data['assigned_group_id'],
            $data['start_date'],
            $data['due_date'],
            $data['status'],
            $data['progress_percentage'],
            $data['estimated_hours'],
            $data['parent_deliverable_id'],
            $tags,
            $data['delivery_confidence'],
            $data['risk_level'],
            $data['risk_notes']
        ];

        $result = Database::query($sql, $params);

        if ($result) {
            $deliverableId = Database::getInstance()->lastInsertId();

            // Log creation in history
            self::logHistory($deliverableId, $ownerId, 'created', null, $title, null,
                'Deliverable created');

            return self::findById($deliverableId);
        }

        return false;
    }

    /**
     * Find deliverable by ID
     *
     * @param int $id Deliverable ID
     * @return array|false Deliverable data or false if not found
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT d.*,
                u1.first_name as owner_first_name, u1.last_name as owner_last_name,
                u2.first_name as assigned_first_name, u2.last_name as assigned_last_name,
                g.name as assigned_group_name
                FROM deliverables d
                LEFT JOIN users u1 ON d.owner_id = u1.id
                LEFT JOIN users u2 ON d.assigned_to = u2.id
                LEFT JOIN groups g ON d.assigned_group_id = g.id
                WHERE d.id = ? AND d.tenant_id = ?";

        $result = Database::query($sql, [$id, $tenantId])->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Decode JSON fields
            $result = self::decodeJsonFields($result);
        }

        return $result;
    }

    /**
     * Update deliverable
     *
     * @param int $id Deliverable ID
     * @param array $data Fields to update (whitelisted)
     * @param int|null $userId User making the update (for history)
     * @return bool Success status
     */
    public static function update($id, $data, $userId = null)
    {
        $tenantId = TenantContext::getId();

        // Whitelist of allowed fields
        $allowedFields = [
            'title', 'description', 'category', 'priority', 'assigned_to',
            'assigned_group_id', 'start_date', 'due_date', 'status',
            'progress_percentage', 'estimated_hours', 'actual_hours',
            'parent_deliverable_id', 'tags', 'custom_fields',
            'delivery_confidence', 'risk_level', 'risk_notes',
            'blocking_deliverable_ids', 'depends_on_deliverable_ids',
            'watchers', 'collaborators', 'attachment_urls', 'external_links',
            'completed_at'
        ];

        // Get current state for history
        $oldState = self::findById($id);
        if (!$oldState) {
            return false;
        }

        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                // Convert arrays to JSON
                if (in_array($field, ['tags', 'custom_fields', 'blocking_deliverable_ids',
                    'depends_on_deliverable_ids', 'watchers', 'collaborators',
                    'attachment_urls', 'external_links']) && is_array($value)) {
                    $value = json_encode($value);
                }

                $updates[] = "$field = ?";
                $params[] = $value;

                // Log specific changes to history
                if ($userId && isset($oldState[$field]) && $oldState[$field] != $value) {
                    self::logHistory($id, $userId, 'metadata_updated',
                        $oldState[$field], $value, $field,
                        "Updated $field");
                }
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $tenantId;
        $params[] = $id;

        $sql = "UPDATE deliverables SET " . implode(', ', $updates) .
               " WHERE tenant_id = ? AND id = ?";

        $result = Database::query($sql, $params);

        return $result !== false;
    }

    /**
     * Update deliverable status
     *
     * @param int $id Deliverable ID
     * @param string $newStatus New status value
     * @param int $userId User making the change
     * @return bool Success status
     */
    public static function updateStatus($id, $newStatus, $userId)
    {
        $tenantId = TenantContext::getId();
        $oldState = self::findById($id);

        if (!$oldState) {
            return false;
        }

        $sql = "UPDATE deliverables SET status = ?,
                completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
                WHERE id = ? AND tenant_id = ?";

        $result = Database::query($sql, [$newStatus, $newStatus, $id, $tenantId]);

        if ($result !== false) {
            // Log status change
            self::logHistory($id, $userId, 'status_changed',
                $oldState['status'], $newStatus, 'status',
                "Status changed from {$oldState['status']} to {$newStatus}");
        }

        return $result !== false;
    }

    /**
     * Update progress percentage
     *
     * @param int $id Deliverable ID
     * @param float $percentage Progress percentage (0-100)
     * @param int $userId User making the update
     * @return bool Success status
     */
    public static function updateProgress($id, $percentage, $userId)
    {
        $tenantId = TenantContext::getId();
        $oldState = self::findById($id);

        if (!$oldState) {
            return false;
        }

        $percentage = max(0, min(100, $percentage)); // Clamp to 0-100

        $sql = "UPDATE deliverables SET progress_percentage = ?
                WHERE id = ? AND tenant_id = ?";

        $result = Database::query($sql, [$percentage, $id, $tenantId]);

        if ($result !== false) {
            self::logHistory($id, $userId, 'progress_updated',
                $oldState['progress_percentage'], $percentage, 'progress_percentage',
                "Progress updated to {$percentage}%");
        }

        return $result !== false;
    }

    /**
     * Assign deliverable to user or group
     *
     * @param int $id Deliverable ID
     * @param int|null $userId User ID to assign to
     * @param int|null $groupId Group ID to assign to
     * @param int $assignedBy User making the assignment
     * @return bool Success status
     */
    public static function assign($id, $userId = null, $groupId = null, $assignedBy)
    {
        $tenantId = TenantContext::getId();
        $oldState = self::findById($id);

        if (!$oldState) {
            return false;
        }

        $sql = "UPDATE deliverables SET assigned_to = ?, assigned_group_id = ?
                WHERE id = ? AND tenant_id = ?";

        $result = Database::query($sql, [$userId, $groupId, $id, $tenantId]);

        if ($result !== false) {
            $action = $oldState['assigned_to'] ? 'reassigned' : 'assigned';
            $description = $userId ? "Assigned to user ID: $userId" : "Assigned to group ID: $groupId";

            self::logHistory($id, $assignedBy, $action,
                $oldState['assigned_to'] ?? $oldState['assigned_group_id'],
                $userId ?? $groupId, 'assigned_to', $description);
        }

        return $result !== false;
    }

    /**
     * Delete deliverable (soft delete by status change recommended)
     *
     * @param int $id Deliverable ID
     * @param int $userId User performing deletion
     * @return bool Success status
     */
    public static function delete($id, $userId)
    {
        $tenantId = TenantContext::getId();

        // Log deletion before removing
        self::logHistory($id, $userId, 'cancelled', null, null, null, 'Deliverable deleted');

        $sql = "DELETE FROM deliverables WHERE id = ? AND tenant_id = ?";
        $result = Database::query($sql, [$id, $tenantId]);

        return $result !== false;
    }

    /**
     * Get all deliverables for tenant with filters
     *
     * @param array $filters Optional filters (status, assigned_to, owner_id, etc.)
     * @param int $limit Results limit
     * @param int $offset Pagination offset
     * @return array List of deliverables
     */
    public static function getAll($filters = [], $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        $where = ["d.tenant_id = ?"];
        $params = [$tenantId];

        // Apply filters
        if (!empty($filters['status'])) {
            $where[] = "d.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = "d.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        if (!empty($filters['owner_id'])) {
            $where[] = "d.owner_id = ?";
            $params[] = $filters['owner_id'];
        }

        if (!empty($filters['priority'])) {
            $where[] = "d.priority = ?";
            $params[] = $filters['priority'];
        }

        if (!empty($filters['category'])) {
            $where[] = "d.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['assigned_group_id'])) {
            $where[] = "d.assigned_group_id = ?";
            $params[] = $filters['assigned_group_id'];
        }

        if (isset($filters['overdue']) && $filters['overdue']) {
            $where[] = "d.due_date < NOW() AND d.status NOT IN ('completed', 'cancelled')";
        }

        // Ensure limit and offset are integers
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT d.*,
                u1.first_name as owner_first_name, u1.last_name as owner_last_name,
                u2.first_name as assigned_first_name, u2.last_name as assigned_last_name,
                g.name as assigned_group_name
                FROM deliverables d
                LEFT JOIN users u1 ON d.owner_id = u1.id
                LEFT JOIN users u2 ON d.assigned_to = u2.id
                LEFT JOIN groups g ON d.assigned_group_id = g.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.priority DESC, d.due_date ASC
                LIMIT {$limit} OFFSET {$offset}";

        $results = Database::query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'decodeJsonFields'], $results);
    }

    /**
     * Get deliverables count with filters
     *
     * @param array $filters Optional filters
     * @return int Count of deliverables
     */
    public static function getCount($filters = [])
    {
        $tenantId = TenantContext::getId();

        $where = ["tenant_id = ?"];
        $params = [$tenantId];

        // Apply same filters as getAll
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = "assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        if (!empty($filters['owner_id'])) {
            $where[] = "owner_id = ?";
            $params[] = $filters['owner_id'];
        }

        $sql = "SELECT COUNT(*) as count FROM deliverables
                WHERE " . implode(' AND ', $where);

        $result = Database::query($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return (int) $result['count'];
    }

    /**
     * Get dashboard statistics
     *
     * @param int|null $userId Optional user filter
     * @return array Statistics array
     */
    public static function getStats($userId = null)
    {
        $tenantId = TenantContext::getId();

        $userFilter = $userId ? "AND (owner_id = ? OR assigned_to = ?)" : "";
        $params = $userId ? [$tenantId, $userId, $userId] : [$tenantId];

        $sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as in_review,
                SUM(CASE WHEN due_date < NOW() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                AVG(progress_percentage) as avg_progress
                FROM deliverables
                WHERE tenant_id = ? $userFilter";

        return Database::query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Log action to deliverable history
     *
     * @param int $deliverableId Deliverable ID
     * @param int $userId User performing action
     * @param string $actionType Type of action
     * @param mixed $oldValue Previous value
     * @param mixed $newValue New value
     * @param string|null $fieldName Field that changed
     * @param string|null $description Human-readable description
     * @return bool Success status
     */
    public static function logHistory($deliverableId, $userId, $actionType, $oldValue = null,
                                     $newValue = null, $fieldName = null, $description = null)
    {
        $tenantId = TenantContext::getId();

        // Convert values to JSON if they're arrays
        $oldValueStr = is_array($oldValue) ? json_encode($oldValue) : $oldValue;
        $newValueStr = is_array($newValue) ? json_encode($newValue) : $newValue;

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO deliverable_history (
            tenant_id, deliverable_id, action_type, user_id,
            old_value, new_value, field_name, change_description,
            ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $tenantId, $deliverableId, $actionType, $userId,
            $oldValueStr, $newValueStr, $fieldName, $description,
            $ipAddress, $userAgent
        ];

        $result = Database::query($sql, $params);
        return $result !== false;
    }

    /**
     * Get deliverable history
     *
     * @param int $deliverableId Deliverable ID
     * @param int $limit Results limit
     * @return array History entries
     */
    public static function getHistory($deliverableId, $limit = 100)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;

        $sql = "SELECT h.*, u.first_name, u.last_name
                FROM deliverable_history h
                LEFT JOIN users u ON h.user_id = u.id
                WHERE h.deliverable_id = ? AND h.tenant_id = ?
                ORDER BY h.action_timestamp DESC
                LIMIT {$limit}";

        return Database::query($sql, [$deliverableId, $tenantId])
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Decode JSON fields in deliverable data
     *
     * @param array $deliverable Deliverable data
     * @return array Deliverable with decoded JSON fields
     */
    private static function decodeJsonFields($deliverable)
    {
        $jsonFields = [
            'tags', 'custom_fields', 'blocking_deliverable_ids',
            'depends_on_deliverable_ids', 'watchers', 'collaborators',
            'attachment_urls', 'external_links'
        ];

        foreach ($jsonFields as $field) {
            if (isset($deliverable[$field]) && is_string($deliverable[$field])) {
                $deliverable[$field] = json_decode($deliverable[$field], true);
            }
        }

        return $deliverable;
    }
}
