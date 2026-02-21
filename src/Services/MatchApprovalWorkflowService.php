<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\NotificationDispatcher;

/**
 * MatchApprovalWorkflowService
 *
 * Manages broker approval workflow for matches.
 * All matches require broker approval before being shown to users.
 * Users are notified when matches are approved or rejected (with reason).
 *
 * Design Decisions:
 * - ALL matches require approval (no score threshold)
 * - Users ARE notified on rejection with reason
 */
class MatchApprovalWorkflowService
{
    // Approval statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Submit a match for broker approval
     *
     * @param int $userId User who would receive this match
     * @param int $listingId The matched listing
     * @param array $matchData Match details from SmartMatchingEngine
     * @return int|null Approval request ID
     */
    public static function submitForApproval(int $userId, int $listingId, array $matchData): ?int
    {
        $tenantId = TenantContext::getId();
        self::ensureTableExists();

        try {
            // Check if approval already exists for this user/listing pair
            $existing = Database::query(
                "SELECT id FROM match_approvals
                 WHERE tenant_id = ? AND user_id = ? AND listing_id = ? AND status = ?",
                [$tenantId, $userId, $listingId, self::STATUS_PENDING]
            )->fetch();

            if ($existing) {
                return $existing['id'];
            }

            // Get listing owner
            $listing = Database::query(
                "SELECT user_id, title FROM listings WHERE id = ?",
                [$listingId]
            )->fetch();

            if (!$listing) {
                error_log("MatchApprovalWorkflowService: Listing $listingId not found");
                return null;
            }

            // Create approval request
            Database::query(
                "INSERT INTO match_approvals
                 (tenant_id, user_id, listing_id, listing_owner_id, match_score, match_type, match_reasons, distance_km, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $tenantId,
                    $userId,
                    $listingId,
                    $listing['user_id'],
                    $matchData['match_score'] ?? 0,
                    $matchData['match_type'] ?? 'one_way',
                    json_encode($matchData['match_reasons'] ?? []),
                    $matchData['distance_km'] ?? null,
                    self::STATUS_PENDING
                ]
            );

            $requestId = Database::lastInsertId();

            // Notify brokers/admins
            self::notifyBrokers($requestId, $userId, $listing);

            // Log audit (null org_id — not an org operation)
            AuditLogService::log(
                'match_submitted_for_approval',
                null,
                $requestId,
                [
                    'user_id' => $userId,
                    'listing_id' => $listingId,
                    'match_score' => $matchData['match_score'] ?? 0
                ]
            );

            return $requestId;
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to submit for approval - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Approve a match - user will be notified
     *
     * @param int $requestId Approval request ID
     * @param int $approvedBy User ID of broker/admin
     * @param string $notes Optional approval notes
     * @return bool Success
     */
    public static function approveMatch(int $requestId, int $approvedBy, string $notes = ''): bool
    {
        try {
            $request = self::getRequest($requestId);
            if (!$request) {
                return false;
            }

            if ($request['status'] !== self::STATUS_PENDING) {
                error_log("MatchApprovalWorkflowService: Request $requestId is not pending");
                return false;
            }

            // Update approval request
            Database::query(
                "UPDATE match_approvals
                 SET status = ?,
                     reviewed_by = ?,
                     review_notes = ?,
                     reviewed_at = NOW()
                 WHERE id = ?",
                [self::STATUS_APPROVED, $approvedBy, $notes, $requestId]
            );

            // Notify the user about the approved match
            self::notifyUserApproved($request);

            // Log audit (null org_id — not an org operation)
            AuditLogService::log(
                'match_approved',
                null,
                $requestId,
                [
                    'approved_by' => $approvedBy,
                    'user_id' => $request['user_id'],
                    'listing_id' => $request['listing_id']
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to approve match - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a match - user will be notified with reason
     *
     * @param int $requestId Approval request ID
     * @param int $rejectedBy User ID of broker/admin
     * @param string $reason Rejection reason (shown to user)
     * @return bool Success
     */
    public static function rejectMatch(int $requestId, int $rejectedBy, string $reason = ''): bool
    {
        try {
            $request = self::getRequest($requestId);
            if (!$request) {
                return false;
            }

            if ($request['status'] !== self::STATUS_PENDING) {
                error_log("MatchApprovalWorkflowService: Request $requestId is not pending");
                return false;
            }

            // Update approval request
            Database::query(
                "UPDATE match_approvals
                 SET status = ?,
                     reviewed_by = ?,
                     review_notes = ?,
                     reviewed_at = NOW()
                 WHERE id = ?",
                [self::STATUS_REJECTED, $rejectedBy, $reason, $requestId]
            );

            // Notify the user about the rejection (with reason)
            self::notifyUserRejected($request, $reason);

            // Log audit (null org_id — not an org operation)
            AuditLogService::log(
                'match_rejected',
                null,
                $requestId,
                [
                    'rejected_by' => $rejectedBy,
                    'user_id' => $request['user_id'],
                    'listing_id' => $request['listing_id'],
                    'reason' => $reason
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to reject match - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk approve multiple matches
     *
     * @param array $requestIds Array of request IDs
     * @param int $approvedBy User ID of broker/admin
     * @param string $notes Optional notes
     * @return int Number of successfully approved matches
     */
    public static function bulkApprove(array $requestIds, int $approvedBy, string $notes = ''): int
    {
        $count = 0;
        foreach ($requestIds as $requestId) {
            if (self::approveMatch((int)$requestId, $approvedBy, $notes)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Bulk reject multiple matches
     *
     * @param array $requestIds Array of request IDs
     * @param int $rejectedBy User ID of broker/admin
     * @param string $reason Rejection reason
     * @return int Number of successfully rejected matches
     */
    public static function bulkReject(array $requestIds, int $rejectedBy, string $reason = ''): int
    {
        $count = 0;
        foreach ($requestIds as $requestId) {
            if (self::rejectMatch((int)$requestId, $rejectedBy, $reason)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get a single approval request
     *
     * @param int $requestId Request ID
     * @return array|null Request data
     */
    public static function getRequest(int $requestId): ?array
    {
        try {
            $request = Database::query(
                "SELECT * FROM match_approvals WHERE id = ?",
                [$requestId]
            )->fetch();

            return $request ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get pending approval requests with full details
     *
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Requests with user and listing details
     */
    public static function getPendingRequests(int $limit = 50, int $offset = 0): array
    {
        $limit = (int)$limit;
        $offset = (int)$offset;
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT ma.*,
                        u.first_name as user_first_name,
                        u.last_name as user_last_name,
                        u.email as user_email,
                        u.avatar_url as user_avatar,
                        CONCAT(u.first_name, ' ', u.last_name) as user_name,
                        l.title as listing_title,
                        l.description as listing_description,
                        l.type as listing_type,
                        l.category_id,
                        c.name as category_name,
                        o.first_name as owner_first_name,
                        o.last_name as owner_last_name,
                        o.avatar_url as owner_avatar,
                        CONCAT(o.first_name, ' ', o.last_name) as owner_name
                 FROM match_approvals ma
                 JOIN users u ON ma.user_id = u.id
                 JOIN listings l ON ma.listing_id = l.id
                 LEFT JOIN categories c ON l.category_id = c.id
                 JOIN users o ON ma.listing_owner_id = o.id
                 WHERE ma.tenant_id = ? AND ma.status = ?
                 ORDER BY ma.submitted_at ASC
                 LIMIT $limit OFFSET $offset",
                [$tenantId, self::STATUS_PENDING]
            )->fetchAll();
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to get pending requests - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get approval history (non-pending requests)
     *
     * @param array $filters Optional filters (status, reviewer_id)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array History
     */
    public static function getApprovalHistory(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $limit = (int)$limit;
        $offset = (int)$offset;
        $tenantId = TenantContext::getId();

        $sql = "SELECT ma.*,
                       u.first_name as user_first_name,
                       u.last_name as user_last_name,
                       CONCAT(u.first_name, ' ', u.last_name) as user_name,
                       l.title as listing_title,
                       r.first_name as reviewer_first_name,
                       r.last_name as reviewer_last_name,
                       CONCAT(r.first_name, ' ', r.last_name) as reviewer_name
                FROM match_approvals ma
                JOIN users u ON ma.user_id = u.id
                LEFT JOIN listings l ON ma.listing_id = l.id
                LEFT JOIN users r ON ma.reviewed_by = r.id
                WHERE ma.tenant_id = ? AND ma.status != ?";

        $params = [$tenantId, self::STATUS_PENDING];

        if (!empty($filters['status'])) {
            $sql .= " AND ma.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['reviewer_id'])) {
            $sql .= " AND ma.reviewed_by = ?";
            $params[] = $filters['reviewer_id'];
        }

        $sql .= " ORDER BY ma.reviewed_at DESC LIMIT $limit OFFSET $offset";

        try {
            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get approval statistics
     *
     * @param int $days Days to analyze
     * @return array Statistics
     */
    public static function getStatistics(int $days = 30): array
    {
        $tenantId = TenantContext::getId();

        try {
            $stats = [
                'pending_count' => 0,
                'approved_count' => 0,
                'rejected_count' => 0,
                'avg_approval_time' => 0,
                'approval_rate' => 0,
            ];

            // Count by status
            $counts = Database::query(
                "SELECT status, COUNT(*) as count
                 FROM match_approvals
                 WHERE tenant_id = ?
                 AND submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY status",
                [$tenantId, $days]
            )->fetchAll();

            foreach ($counts as $row) {
                $stats[$row['status'] . '_count'] = (int)$row['count'];
            }

            // Average approval time (in hours)
            $avgTime = Database::query(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, submitted_at, reviewed_at)) as avg_time
                 FROM match_approvals
                 WHERE tenant_id = ?
                 AND status IN (?, ?)
                 AND reviewed_at IS NOT NULL
                 AND submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$tenantId, self::STATUS_APPROVED, self::STATUS_REJECTED, $days]
            )->fetchColumn();

            $stats['avg_approval_time'] = round($avgTime ?? 0, 1);

            // Approval rate
            $total = $stats['approved_count'] + $stats['rejected_count'];
            $stats['approval_rate'] = $total > 0 ? round(($stats['approved_count'] / $total) * 100, 1) : 0;

            return $stats;
        } catch (\Exception $e) {
            return [
                'pending_count' => 0,
                'approved_count' => 0,
                'rejected_count' => 0,
                'avg_approval_time' => 0,
                'approval_rate' => 0,
            ];
        }
    }

    /**
     * Count pending requests
     *
     * @return int Count
     */
    public static function getPendingCount(): int
    {
        $tenantId = TenantContext::getId();

        try {
            return (int)Database::query(
                "SELECT COUNT(*) FROM match_approvals WHERE tenant_id = ? AND status = ?",
                [$tenantId, self::STATUS_PENDING]
            )->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if a match is approved (for use in matching engine)
     *
     * @param int $userId User ID
     * @param int $listingId Listing ID
     * @return bool True if approved, false if pending/rejected/not found
     */
    public static function isMatchApproved(int $userId, int $listingId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $status = Database::query(
                "SELECT status FROM match_approvals
                 WHERE tenant_id = ? AND user_id = ? AND listing_id = ?
                 ORDER BY submitted_at DESC LIMIT 1",
                [$tenantId, $userId, $listingId]
            )->fetchColumn();

            return $status === self::STATUS_APPROVED;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Notify brokers/admins of new match pending approval
     */
    private static function notifyBrokers(int $requestId, int $userId, array $listing): void
    {
        $tenantId = TenantContext::getId();

        try {
            // Get user name
            $user = Database::query(
                "SELECT first_name, last_name FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            $userName = $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'A member';
            $listingTitle = $listing['title'] ?? 'Unknown Listing';

            // Get all admins/brokers
            $admins = Database::query(
                "SELECT id FROM users
                 WHERE tenant_id = ?
                 AND role IN ('super_admin', 'admin', 'tenant_admin', 'broker')",
                [$tenantId]
            )->fetchAll();

            foreach ($admins as $admin) {
                NotificationDispatcher::dispatchMatchApprovalRequest(
                    $admin['id'],
                    $userName,
                    $listingTitle,
                    $requestId
                );
            }
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to notify brokers - " . $e->getMessage());
        }
    }

    /**
     * Notify user that their match has been approved
     */
    private static function notifyUserApproved(array $request): void
    {
        try {
            $listing = Database::query(
                "SELECT title FROM listings WHERE id = ?",
                [$request['listing_id']]
            )->fetch();

            $listingTitle = $listing['title'] ?? 'a listing';
            $matchScore = (float)($request['match_score'] ?? 0);

            NotificationDispatcher::dispatchMatchApproved(
                $request['user_id'],
                $listingTitle,
                $request['listing_id'],
                $matchScore
            );

            // Also send email notification for hot matches (80%+ score)
            if ($matchScore >= 80) {
                NotificationDispatcher::dispatchHotMatch(
                    $request['user_id'],
                    $request['listing_id'],
                    $matchScore
                );
            }
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to notify user of approval - " . $e->getMessage());
        }
    }

    /**
     * Notify user that their match has been rejected (with reason)
     */
    private static function notifyUserRejected(array $request, string $reason): void
    {
        try {
            $listing = Database::query(
                "SELECT title FROM listings WHERE id = ?",
                [$request['listing_id']]
            )->fetch();

            $listingTitle = $listing['title'] ?? 'a listing';

            NotificationDispatcher::dispatchMatchRejected(
                $request['user_id'],
                $listingTitle,
                $reason
            );
        } catch (\Exception $e) {
            error_log("MatchApprovalWorkflowService: Failed to notify user of rejection - " . $e->getMessage());
        }
    }

    /**
     * Ensure match_approvals table exists
     */
    private static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            Database::query("SELECT 1 FROM match_approvals LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS match_approvals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT NOT NULL,
                    listing_id INT NOT NULL,
                    listing_owner_id INT NOT NULL,
                    match_score DECIMAL(5,2) NOT NULL,
                    match_type VARCHAR(50) DEFAULT 'one_way',
                    match_reasons JSON,
                    distance_km DECIMAL(8,2),
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    reviewed_by INT NULL,
                    reviewed_at TIMESTAMP NULL,
                    review_notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_tenant_status (tenant_id, status),
                    INDEX idx_pending (tenant_id, status, submitted_at),
                    INDEX idx_user (user_id),
                    INDEX idx_listing (listing_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
