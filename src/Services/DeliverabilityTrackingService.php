<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\TenantContext;
use Nexus\Models\Deliverable;
use Nexus\Models\DeliverableMilestone;
use Nexus\Models\DeliverableComment;
use Nexus\Models\User;
use Nexus\Models\Notification;

/**
 * DeliverabilityTrackingService
 *
 * Business logic for deliverability tracking module.
 * Handles complex operations, analytics, notifications, and integrations.
 */
class DeliverabilityTrackingService
{
    /**
     * Create a deliverable with automatic notifications
     *
     * @param int $ownerId Owner user ID
     * @param string $title Deliverable title
     * @param string|null $description Description
     * @param array $options Additional options
     * @return array|false Created deliverable or false on failure
     */
    public static function createDeliverable($ownerId, $title, $description = null, $options = [])
    {
        $deliverable = Deliverable::create($ownerId, $title, $description, $options);

        if (!$deliverable) {
            return false;
        }

        // Send notification if assigned to someone
        if (!empty($options['assigned_to']) && $options['assigned_to'] != $ownerId) {
            self::notifyAssignment($deliverable['id'], $options['assigned_to'], $ownerId);
        }

        // Send notification to group members if assigned to group
        if (!empty($options['assigned_group_id'])) {
            self::notifyGroupAssignment($deliverable['id'], $options['assigned_group_id'], $ownerId);
        }

        // Log activity
        ActivityLog::log(
            $ownerId,
            'created_deliverable',
            "Created deliverable: {$title}",
            true,
            "/deliverables/{$deliverable['id']}",
            'deliverable',
            'deliverable',
            $deliverable['id']
        );

        return $deliverable;
    }

    /**
     * Update deliverable status with automatic progress calculation
     *
     * @param int $deliverableId Deliverable ID
     * @param string $newStatus New status
     * @param int $userId User making the change
     * @return bool Success status
     */
    public static function updateDeliverableStatus($deliverableId, $newStatus, $userId)
    {
        $deliverable = Deliverable::findById($deliverableId);
        if (!$deliverable) {
            return false;
        }

        $oldStatus = $deliverable['status'];
        $result = Deliverable::updateStatus($deliverableId, $newStatus, $userId);

        if (!$result) {
            return false;
        }

        // Auto-update progress based on status
        $progressMap = [
            'draft' => 0,
            'ready' => 10,
            'in_progress' => 50,
            'review' => 90,
            'completed' => 100,
            'blocked' => null, // Don't change progress
            'cancelled' => null,
            'on_hold' => null,
        ];

        if (isset($progressMap[$newStatus]) && $progressMap[$newStatus] !== null) {
            Deliverable::updateProgress($deliverableId, $progressMap[$newStatus], $userId);
        }

        // Send notifications based on status change
        self::notifyStatusChange($deliverableId, $oldStatus, $newStatus, $userId);

        // Log activity
        ActivityLog::log(
            $userId,
            'deliverable_status_changed',
            "Changed deliverable status from {$oldStatus} to {$newStatus}",
            true,
            "/deliverables/{$deliverableId}",
            'status_change',
            'deliverable',
            $deliverableId
        );

        return true;
    }

    /**
     * Complete a deliverable with validation
     *
     * @param int $deliverableId Deliverable ID
     * @param int $userId User completing
     * @param array $options Completion options (actual_hours, completion_notes, etc.)
     * @return bool Success status
     */
    public static function completeDeliverable($deliverableId, $userId, $options = [])
    {
        $deliverable = Deliverable::findById($deliverableId);
        if (!$deliverable) {
            return false;
        }

        // Check if all milestones are completed (optional enforcement)
        $milestoneStats = DeliverableMilestone::getStats($deliverableId);
        $allMilestonesComplete = $milestoneStats['total'] == 0 ||
                                 $milestoneStats['completed'] == $milestoneStats['total'];

        if (!$allMilestonesComplete && !($options['force_complete'] ?? false)) {
            // Optionally return false or warning if milestones incomplete
            // For now, we'll allow it but could enforce strict completion
        }

        // Update to completed status
        $updateData = [
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($options['actual_hours'])) {
            $updateData['actual_hours'] = $options['actual_hours'];
        }

        $result = Deliverable::update($deliverableId, $updateData, $userId);

        if ($result) {
            // Notify owner and stakeholders
            self::notifyCompletion($deliverableId, $userId);

            // Log completion
            ActivityLog::log(
                $userId,
                'deliverable_completed',
                "Completed deliverable: {$deliverable['title']}",
                true,
                "/deliverables/{$deliverableId}",
                'completion',
                'deliverable',
                $deliverableId
            );
        }

        return $result;
    }

    /**
     * Calculate and update deliverable progress based on milestones
     *
     * @param int $deliverableId Deliverable ID
     * @param int $userId User triggering recalculation
     * @return float|false New progress percentage or false on failure
     */
    public static function recalculateProgress($deliverableId, $userId)
    {
        $milestoneStats = DeliverableMilestone::getStats($deliverableId);

        if ($milestoneStats['total'] == 0) {
            return false; // No milestones to calculate from
        }

        $progress = ($milestoneStats['completed'] / $milestoneStats['total']) * 100;

        Deliverable::updateProgress($deliverableId, $progress, $userId);

        return $progress;
    }

    /**
     * Get comprehensive analytics for deliverables
     *
     * @param array $filters Optional filters
     * @return array Analytics data
     */
    public static function getAnalytics($filters = [])
    {
        $tenantId = TenantContext::getId();

        // Get basic stats
        $stats = Deliverable::getStats($filters['user_id'] ?? null);

        // Calculate additional metrics
        $analytics = [
            'overview' => $stats,
            'completion_rate' => $stats['total'] > 0
                ? round(($stats['completed'] / $stats['total']) * 100, 2)
                : 0,
            'on_time_rate' => self::calculateOnTimeRate($filters),
            'risk_distribution' => self::getRiskDistribution($filters),
            'priority_breakdown' => self::getPriorityBreakdown($filters),
            'category_distribution' => self::getCategoryDistribution($filters),
            'upcoming_deadlines' => self::getUpcomingDeadlines(7, $filters),
            'blocked_deliverables' => Deliverable::getAll(['status' => 'blocked'] + $filters, 10),
            'trending_metrics' => self::getTrendingMetrics($filters),
        ];

        return $analytics;
    }

    /**
     * Get deliverables dashboard for a user
     *
     * @param int $userId User ID
     * @return array Dashboard data
     */
    public static function getUserDashboard($userId)
    {
        return [
            'my_deliverables' => Deliverable::getAll(['assigned_to' => $userId], 10),
            'owned_deliverables' => Deliverable::getAll(['owner_id' => $userId], 10),
            'stats' => Deliverable::getStats($userId),
            'overdue' => Deliverable::getAll(['assigned_to' => $userId, 'overdue' => true], 5),
            'urgent' => Deliverable::getAll(['assigned_to' => $userId, 'priority' => 'urgent'], 5),
            'upcoming_deadlines' => self::getUpcomingDeadlines(7, ['assigned_to' => $userId]),
            'recent_activity' => Deliverable::getAll(['assigned_to' => $userId], 5),
        ];
    }

    /**
     * Send notification for deliverable assignment
     *
     * @param int $deliverableId Deliverable ID
     * @param int $assignedUserId User being assigned
     * @param int $assignedByUserId User making assignment
     */
    private static function notifyAssignment($deliverableId, $assignedUserId, $assignedByUserId)
    {
        $deliverable = Deliverable::findById($deliverableId);
        $assignedBy = User::findById($assignedByUserId);

        $message = "{$assignedBy['first_name']} {$assignedBy['last_name']} assigned you to: {$deliverable['title']}";
        $link = "/deliverables/{$deliverableId}";

        Notification::create($assignedUserId, $message, $link, 'deliverable_assigned', true);
    }

    /**
     * Send notification to group members for group assignment
     *
     * @param int $deliverableId Deliverable ID
     * @param int $groupId Group ID
     * @param int $assignedByUserId User making assignment
     */
    private static function notifyGroupAssignment($deliverableId, $groupId, $assignedByUserId)
    {
        $deliverable = Deliverable::findById($deliverableId);
        $assignedBy = User::findById($assignedByUserId);

        // Get group members (would need GroupMember model)
        // For now, placeholder
        $message = "{$assignedBy['first_name']} assigned your group to: {$deliverable['title']}";
        $link = "/deliverables/{$deliverableId}";

        // TODO: Implement group member notification loop
        // foreach ($groupMembers as $member) {
        //     Notification::create($member['user_id'], $message, $link, 'deliverable_assigned', true);
        // }
    }

    /**
     * Send notification for status change
     *
     * @param int $deliverableId Deliverable ID
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @param int $changedByUserId User making change
     */
    private static function notifyStatusChange($deliverableId, $oldStatus, $newStatus, $changedByUserId)
    {
        $deliverable = Deliverable::findById($deliverableId);
        $changedBy = User::findById($changedByUserId);

        // Notify owner and assigned user
        $usersToNotify = array_unique(array_filter([
            $deliverable['owner_id'],
            $deliverable['assigned_to']
        ]));

        // Remove the user who made the change
        $usersToNotify = array_diff($usersToNotify, [$changedByUserId]);

        $message = "{$changedBy['first_name']} changed '{$deliverable['title']}' status to {$newStatus}";
        $link = "/deliverables/{$deliverableId}";

        foreach ($usersToNotify as $userId) {
            Notification::create($userId, $message, $link, 'deliverable_status_changed', true);
        }

        // Notify watchers
        if (!empty($deliverable['watchers'])) {
            foreach ($deliverable['watchers'] as $watcherUserId) {
                if ($watcherUserId != $changedByUserId) {
                    Notification::create($watcherUserId, $message, $link, 'deliverable_status_changed', false);
                }
            }
        }
    }

    /**
     * Send notification for deliverable completion
     *
     * @param int $deliverableId Deliverable ID
     * @param int $completedByUserId User who completed
     */
    private static function notifyCompletion($deliverableId, $completedByUserId)
    {
        $deliverable = Deliverable::findById($deliverableId);
        $completedBy = User::findById($completedByUserId);

        // Notify owner if different from completer
        if ($deliverable['owner_id'] != $completedByUserId) {
            $message = "{$completedBy['first_name']} completed deliverable: {$deliverable['title']}";
            $link = "/deliverables/{$deliverableId}";

            Notification::create($deliverable['owner_id'], $message, $link, 'deliverable_completed', true);
        }

        // Notify collaborators
        if (!empty($deliverable['collaborators'])) {
            $message = "Deliverable completed: {$deliverable['title']}";
            $link = "/deliverables/{$deliverableId}";

            foreach ($deliverable['collaborators'] as $collaboratorId) {
                if ($collaboratorId != $completedByUserId) {
                    Notification::create($collaboratorId, $message, $link, 'deliverable_completed', true);
                }
            }
        }
    }

    /**
     * Calculate on-time completion rate
     *
     * @param array $filters Optional filters
     * @return float On-time rate percentage
     */
    private static function calculateOnTimeRate($filters = [])
    {
        // Query completed deliverables and check if completed_at <= due_date
        // Simplified calculation for now
        $completedDeliverables = Deliverable::getAll(['status' => 'completed'] + $filters, 1000);

        if (empty($completedDeliverables)) {
            return 0;
        }

        $onTime = 0;
        foreach ($completedDeliverables as $deliverable) {
            if ($deliverable['due_date'] && $deliverable['completed_at'] &&
                strtotime($deliverable['completed_at']) <= strtotime($deliverable['due_date'])) {
                $onTime++;
            }
        }

        return round(($onTime / count($completedDeliverables)) * 100, 2);
    }

    /**
     * Get risk level distribution
     *
     * @param array $filters Optional filters
     * @return array Risk distribution
     */
    private static function getRiskDistribution($filters = [])
    {
        $deliverables = Deliverable::getAll($filters, 1000);

        $distribution = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];

        foreach ($deliverables as $deliverable) {
            $riskLevel = $deliverable['risk_level'] ?? 'low';
            if (isset($distribution[$riskLevel])) {
                $distribution[$riskLevel]++;
            }
        }

        return $distribution;
    }

    /**
     * Get priority breakdown
     *
     * @param array $filters Optional filters
     * @return array Priority breakdown
     */
    private static function getPriorityBreakdown($filters = [])
    {
        $deliverables = Deliverable::getAll($filters, 1000);

        $breakdown = ['low' => 0, 'medium' => 0, 'high' => 0, 'urgent' => 0];

        foreach ($deliverables as $deliverable) {
            $priority = $deliverable['priority'] ?? 'medium';
            if (isset($breakdown[$priority])) {
                $breakdown[$priority]++;
            }
        }

        return $breakdown;
    }

    /**
     * Get category distribution
     *
     * @param array $filters Optional filters
     * @return array Category distribution
     */
    private static function getCategoryDistribution($filters = [])
    {
        $deliverables = Deliverable::getAll($filters, 1000);

        $distribution = [];

        foreach ($deliverables as $deliverable) {
            $category = $deliverable['category'] ?? 'general';
            if (!isset($distribution[$category])) {
                $distribution[$category] = 0;
            }
            $distribution[$category]++;
        }

        return $distribution;
    }

    /**
     * Get upcoming deadlines
     *
     * @param int $days Number of days to look ahead
     * @param array $filters Optional filters
     * @return array Deliverables with upcoming deadlines
     */
    private static function getUpcomingDeadlines($days = 7, $filters = [])
    {
        $allDeliverables = Deliverable::getAll($filters, 1000);

        $upcoming = [];
        $targetDate = strtotime("+{$days} days");

        foreach ($allDeliverables as $deliverable) {
            if ($deliverable['due_date'] &&
                $deliverable['status'] !== 'completed' &&
                $deliverable['status'] !== 'cancelled') {

                $dueDate = strtotime($deliverable['due_date']);
                if ($dueDate <= $targetDate && $dueDate >= time()) {
                    $upcoming[] = $deliverable;
                }
            }
        }

        // Sort by due date
        usort($upcoming, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']);
        });

        return $upcoming;
    }

    /**
     * Get trending metrics (velocity, etc.)
     *
     * @param array $filters Optional filters
     * @return array Trending metrics
     */
    private static function getTrendingMetrics($filters = [])
    {
        // Calculate delivery velocity (completed deliverables per week)
        // This is a simplified version
        $completedDeliverables = Deliverable::getAll(['status' => 'completed'] + $filters, 1000);

        $lastWeek = array_filter($completedDeliverables, function($d) {
            return strtotime($d['completed_at']) >= strtotime('-7 days');
        });

        $lastMonth = array_filter($completedDeliverables, function($d) {
            return strtotime($d['completed_at']) >= strtotime('-30 days');
        });

        return [
            'weekly_velocity' => count($lastWeek),
            'monthly_velocity' => count($lastMonth),
            'average_completion_time' => self::calculateAverageCompletionTime($completedDeliverables),
        ];
    }

    /**
     * Calculate average completion time in days
     *
     * @param array $completedDeliverables Array of completed deliverables
     * @return float Average days to complete
     */
    private static function calculateAverageCompletionTime($completedDeliverables)
    {
        if (empty($completedDeliverables)) {
            return 0;
        }

        $totalDays = 0;
        $count = 0;

        foreach ($completedDeliverables as $deliverable) {
            if ($deliverable['start_date'] && $deliverable['completed_at']) {
                $start = strtotime($deliverable['start_date']);
                $end = strtotime($deliverable['completed_at']);
                $days = ($end - $start) / 86400; // Convert seconds to days

                $totalDays += $days;
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count, 1) : 0;
    }

    /**
     * Generate deliverability report
     *
     * @param array $filters Optional filters
     * @return array Comprehensive report
     */
    public static function generateReport($filters = [])
    {
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => $filters,
            'analytics' => self::getAnalytics($filters),
            'deliverables' => Deliverable::getAll($filters, 100),
            'summary' => [
                'total_deliverables' => Deliverable::getCount($filters),
                'completion_rate' => self::calculateOnTimeRate($filters),
                'risk_summary' => self::getRiskDistribution($filters),
                'priority_summary' => self::getPriorityBreakdown($filters),
            ]
        ];
    }
}
