<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\ActivityLog;
use App\Models\Deliverable;
use App\Models\DeliverableMilestone;
use Illuminate\Support\Facades\DB;

/**
 * DeliverabilityTrackingService
 *
 * Business logic for deliverability tracking module.
 * Handles complex operations, analytics, notifications, and integrations.
 *
 * Uses App\Models\ActivityLog (not Nexus\Services\ActivityLog) for activity logging.
 */
class DeliverabilityTrackingService
{
    /**
     * Create a deliverable with automatic notifications and activity logging.
     *
     * @param int $ownerId Owner user ID
     * @param string $title Deliverable title
     * @param string|null $description Description
     * @param array $options Additional options (status, priority, assigned_to, assigned_group_id, etc.)
     * @return array|false Created deliverable data or false on failure
     */
    public static function createDeliverable(int $ownerId, string $title, ?string $description = null, array $options = []): array|false
    {
        $tenantId = TenantContext::getId();

        $deliverable = Deliverable::create([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'title' => $title,
            'description' => $description,
            'status' => $options['status'] ?? 'draft',
            'priority' => $options['priority'] ?? 'medium',
            'assigned_to' => $options['assigned_to'] ?? null,
            'assigned_group_id' => $options['assigned_group_id'] ?? null,
            'due_date' => $options['due_date'] ?? null,
        ]);

        if (!$deliverable) {
            return false;
        }

        // Log activity
        ActivityLog::log(
            $ownerId,
            'created_deliverable',
            "Created deliverable: {$title}",
            true,
            "/deliverables/{$deliverable->id}",
            'deliverable',
            'deliverable',
            $deliverable->id
        );

        return $deliverable->toArray();
    }

    /**
     * Update deliverable status with automatic progress calculation.
     *
     * @param int $deliverableId Deliverable ID
     * @param string $newStatus New status
     * @param int $userId User making the change
     * @return bool Success status
     */
    public static function updateDeliverableStatus(int $deliverableId, string $newStatus, int $userId): bool
    {
        $deliverable = Deliverable::where('tenant_id', TenantContext::getId())
            ->find($deliverableId);

        if (!$deliverable) {
            return false;
        }

        $oldStatus = $deliverable->status;
        $deliverable->status = $newStatus;

        // Auto-update progress based on status
        $progressMap = [
            'draft' => 0,
            'ready' => 10,
            'in_progress' => 25,
            'review' => 75,
            'completed' => 100,
            'blocked' => null, // don't change progress
            'cancelled' => null,
        ];

        if (isset($progressMap[$newStatus]) && $progressMap[$newStatus] !== null) {
            $deliverable->progress_percentage = $progressMap[$newStatus];
        }

        if ($newStatus === 'completed') {
            $deliverable->completed_at = now();
        }

        $deliverable->save();

        // Log activity
        ActivityLog::log(
            $userId,
            'updated_deliverable_status',
            "Updated deliverable #{$deliverableId} status from {$oldStatus} to {$newStatus}",
            true,
            "/deliverables/{$deliverableId}",
            'deliverable',
            'deliverable',
            $deliverableId
        );

        return true;
    }

    /**
     * Complete a deliverable with optional notes and activity logging.
     *
     * @param int $deliverableId Deliverable ID
     * @param int $userId User completing the deliverable
     * @param array $options Additional options (notes, actual_hours, etc.)
     * @return bool Success status
     */
    public static function completeDeliverable(int $deliverableId, int $userId, array $options = []): bool
    {
        $deliverable = Deliverable::where('tenant_id', TenantContext::getId())
            ->find($deliverableId);

        if (!$deliverable) {
            return false;
        }

        $deliverable->status = 'completed';
        $deliverable->progress_percentage = 100;
        $deliverable->completed_at = now();

        if (!empty($options['actual_hours'])) {
            $deliverable->actual_hours = $options['actual_hours'];
        }

        $deliverable->save();

        // Log activity
        ActivityLog::log(
            $userId,
            'completed_deliverable',
            "Completed deliverable: {$deliverable->title}",
            true,
            "/deliverables/{$deliverableId}",
            'deliverable',
            'deliverable',
            $deliverableId
        );

        return true;
    }

    /**
     * Recalculate progress based on milestone completion.
     *
     * @param int $deliverableId Deliverable ID
     * @param int $userId User triggering recalculation
     * @return float Progress percentage (0-100)
     */
    public static function recalculateProgress(int $deliverableId, int $userId): float
    {
        $tenantId = TenantContext::getId();

        $totalMilestones = DB::table('deliverable_milestones')
            ->where('deliverable_id', $deliverableId)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalMilestones === 0) {
            return 0.0;
        }

        $completedMilestones = DB::table('deliverable_milestones')
            ->where('deliverable_id', $deliverableId)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->count();

        $progress = round(($completedMilestones / $totalMilestones) * 100);

        // Update the deliverable's progress
        DB::table('deliverables')
            ->where('id', $deliverableId)
            ->where('tenant_id', $tenantId)
            ->update(['progress_percentage' => $progress, 'updated_at' => now()]);

        return (float) $progress;
    }

    /**
     * Get analytics for deliverables in the current tenant.
     *
     * @param array $filters Optional filters
     * @return array Analytics data
     */
    public static function getAnalytics(array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('deliverables')->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $deliverables = $query->get();
        $total = $deliverables->count();
        $completed = $deliverables->where('status', 'completed')->count();
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        $statusCounts = $deliverables->groupBy('status')->map->count()->toArray();

        $riskDistribution = $deliverables->groupBy('risk_level')->map->count()->toArray();
        $priorityBreakdown = $deliverables->groupBy('priority')->map->count()->toArray();
        $categoryDistribution = $deliverables->groupBy('category')->map->count()->toArray();

        return [
            'overview' => [
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $statusCounts['in_progress'] ?? 0,
                'blocked' => $statusCounts['blocked'] ?? 0,
                'draft' => $statusCounts['draft'] ?? 0,
            ],
            'completion_rate' => $completionRate,
            'risk_distribution' => $riskDistribution,
            'priority_breakdown' => $priorityBreakdown,
            'category_distribution' => $categoryDistribution,
        ];
    }

    /**
     * Get user dashboard data for deliverables.
     *
     * @param int $userId User ID
     * @return array Dashboard data
     */
    public static function getUserDashboard(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $myDeliverables = DB::table('deliverables')
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $ownedDeliverables = DB::table('deliverables')
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $allUserDeliverables = DB::table('deliverables')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->orWhere('owner_id', $userId);
            })
            ->get();

        $total = $allUserDeliverables->count();
        $completed = $allUserDeliverables->where('status', 'completed')->count();
        $inProgress = $allUserDeliverables->where('status', 'in_progress')->count();

        $overdue = DB::table('deliverables')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->orWhere('owner_id', $userId);
            })
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $urgent = DB::table('deliverables')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->orWhere('owner_id', $userId);
            })
            ->where('priority', 'urgent')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $upcomingDeadlines = DB::table('deliverables')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->orWhere('owner_id', $userId);
            })
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('due_date')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'my_deliverables' => $myDeliverables,
            'owned_deliverables' => $ownedDeliverables,
            'stats' => [
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
            ],
            'overdue' => $overdue,
            'urgent' => $urgent,
            'upcoming_deadlines' => $upcomingDeadlines,
        ];
    }

    /**
     * Generate a deliverables report.
     *
     * @param array $filters Optional filters (status, priority, etc.)
     * @return array Report data
     */
    public static function generateReport(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $analytics = self::getAnalytics($filters);

        $query = DB::table('deliverables')->where('tenant_id', $tenantId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $deliverables = $query->orderByDesc('updated_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $total = count($deliverables);
        $completed = count(array_filter($deliverables, fn ($d) => $d['status'] === 'completed'));

        return [
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'analytics' => $analytics,
            'deliverables' => $deliverables,
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ],
        ];
    }
}
