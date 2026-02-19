<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use PDO;

/**
 * DeliverableMilestone Model
 *
 * Manages milestones and sub-tasks within deliverables.
 */
class DeliverableMilestone
{
    /**
     * Create a new milestone
     *
     * @param int $deliverableId Parent deliverable ID
     * @param string $title Milestone title
     * @param array $options Additional options
     * @return array|false Created milestone or false on failure
     */
    public static function create($deliverableId, $title, $options = [])
    {
        $tenantId = TenantContext::getId();

        $defaults = [
            'description' => null,
            'order_position' => 0,
            'status' => 'pending',
            'due_date' => null,
            'estimated_hours' => null,
        ];

        $data = array_merge($defaults, $options);

        $sql = "INSERT INTO deliverable_milestones (
            tenant_id, deliverable_id, title, description,
            order_position, status, due_date, estimated_hours
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $tenantId,
            $deliverableId,
            $title,
            $data['description'],
            $data['order_position'],
            $data['status'],
            $data['due_date'],
            $data['estimated_hours']
        ];

        $result = Database::query($sql, $params);

        if ($result) {
            $milestoneId = Database::getInstance()->lastInsertId();
            return self::findById($milestoneId);
        }

        return false;
    }

    /**
     * Find milestone by ID
     *
     * @param int $id Milestone ID
     * @return array|false Milestone data or false if not found
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT * FROM deliverable_milestones
                WHERE id = ? AND tenant_id = ?";

        $result = Database::query($sql, [$id, $tenantId])->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['depends_on_milestone_ids'])) {
            $result['depends_on_milestone_ids'] = json_decode($result['depends_on_milestone_ids'], true);
        }

        return $result;
    }

    /**
     * Update milestone
     *
     * @param int $id Milestone ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();

        $allowedFields = [
            'title', 'description', 'order_position', 'status',
            'due_date', 'estimated_hours', 'depends_on_milestone_ids'
        ];

        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if ($field === 'depends_on_milestone_ids' && is_array($value)) {
                    $value = json_encode($value);
                }

                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $tenantId;
        $params[] = $id;

        $sql = "UPDATE deliverable_milestones SET " . implode(', ', $updates) .
               " WHERE tenant_id = ? AND id = ?";

        return Database::query($sql, $params) !== false;
    }

    /**
     * Mark milestone as completed
     *
     * @param int $id Milestone ID
     * @param int $userId User completing the milestone
     * @return bool Success status
     */
    public static function complete($id, $userId)
    {
        $tenantId = TenantContext::getId();

        $sql = "UPDATE deliverable_milestones
                SET status = 'completed', completed_at = NOW(), completed_by = ?
                WHERE id = ? AND tenant_id = ?";

        $result = Database::query($sql, [$userId, $id, $tenantId]);

        if ($result !== false) {
            // Get milestone to log in parent deliverable history
            $milestone = self::findById($id);
            if ($milestone) {
                Deliverable::logHistory(
                    $milestone['deliverable_id'],
                    $userId,
                    'milestone_completed',
                    null,
                    $milestone['title'],
                    null,
                    "Milestone '{$milestone['title']}' completed"
                );
            }
        }

        return $result !== false;
    }

    /**
     * Delete milestone
     *
     * @param int $id Milestone ID
     * @return bool Success status
     */
    public static function delete($id)
    {
        $tenantId = TenantContext::getId();

        $sql = "DELETE FROM deliverable_milestones WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$id, $tenantId]) !== false;
    }

    /**
     * Get all milestones for a deliverable
     *
     * @param int $deliverableId Deliverable ID
     * @return array List of milestones
     */
    public static function getByDeliverable($deliverableId)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT m.*, u.first_name, u.last_name
                FROM deliverable_milestones m
                LEFT JOIN users u ON m.completed_by = u.id
                WHERE m.deliverable_id = ? AND m.tenant_id = ?
                ORDER BY m.order_position ASC, m.created_at ASC";

        $results = Database::query($sql, [$deliverableId, $tenantId])
            ->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($results as &$result) {
            if (isset($result['depends_on_milestone_ids'])) {
                $result['depends_on_milestone_ids'] = json_decode($result['depends_on_milestone_ids'], true);
            }
        }

        return $results;
    }

    /**
     * Reorder milestones
     *
     * @param array $orderedIds Array of milestone IDs in desired order
     * @return bool Success status
     */
    public static function reorder($orderedIds)
    {
        $tenantId = TenantContext::getId();

        Database::getInstance()->beginTransaction();

        try {
            foreach ($orderedIds as $position => $id) {
                $sql = "UPDATE deliverable_milestones
                        SET order_position = ?
                        WHERE id = ? AND tenant_id = ?";

                Database::query($sql, [$position, $id, $tenantId]);
            }

            Database::getInstance()->commit();
            return true;
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            return false;
        }
    }

    /**
     * Get milestone completion statistics for a deliverable
     *
     * @param int $deliverableId Deliverable ID
     * @return array Statistics
     */
    public static function getStats($deliverableId)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM deliverable_milestones
                WHERE deliverable_id = ? AND tenant_id = ?";

        return Database::query($sql, [$deliverableId, $tenantId])->fetch(PDO::FETCH_ASSOC);
    }
}
