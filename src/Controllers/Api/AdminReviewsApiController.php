<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;

/**
 * AdminReviewsApiController - V2 API for reviews moderation
 *
 * Moderate user reviews.
 * All endpoints require admin authentication.
 *
 * Schema notes:
 * - Uses receiver_id (NOT reviewee_id)
 * - Uses comment TEXT (NOT content)
 * - Status ENUM('pending','approved','rejected') default 'approved'
 * - reports.target_type ENUM('listing','user','message') - does NOT include 'review'
 *
 * Endpoints:
 * - GET    /api/v2/admin/reviews           - List reviews
 * - GET    /api/v2/admin/reviews/{id}      - Get review detail
 * - POST   /api/v2/admin/reviews/{id}/flag - Flag review (set status to pending)
 * - POST   /api/v2/admin/reviews/{id}/hide - Hide review (set status to rejected)
 * - DELETE /api/v2/admin/reviews/{id}      - Delete review
 */
class AdminReviewsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/reviews
     *
     * Query params: page, limit, rating, status, search
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $rating = isset($_GET['rating']) ? (int) $_GET['rating'] : null;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        $conditions = ['r.tenant_id = ?'];
        $params = [$tenantId];

        // Rating filter
        if ($rating !== null && $rating >= 1 && $rating <= 5) {
            $conditions[] = 'r.rating = ?';
            $params[] = $rating;
        }

        // Status filter (pending, approved, rejected)
        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $conditions[] = 'r.status = ?';
            $params[] = $status;
        }

        // Search filter
        if ($search) {
            $conditions[] = '(r.comment LIKE ? OR reviewer.name LIKE ? OR receiver.name LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = implode(' AND ', $conditions);

        // Get total count
        $countQuery = "SELECT COUNT(*) as total
                       FROM reviews r
                       LEFT JOIN users reviewer ON r.reviewer_id = reviewer.id
                       LEFT JOIN users receiver ON r.receiver_id = receiver.id
                       WHERE {$where}";
        $countStmt = Database::query($countQuery, $params);
        $total = (int) $countStmt->fetch()['total'];

        // Get paginated results
        $query = "SELECT r.*,
                         reviewer.name as reviewer_name,
                         reviewer.avatar_url as reviewer_avatar,
                         receiver.name as receiver_name,
                         receiver.avatar_url as receiver_avatar
                  FROM reviews r
                  LEFT JOIN users reviewer ON r.reviewer_id = reviewer.id
                  LEFT JOIN users receiver ON r.receiver_id = receiver.id
                  WHERE {$where}
                  ORDER BY r.created_at DESC
                  LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $stmt = Database::query($query, $params);
        $reviews = $stmt->fetchAll();

        // Format for frontend
        $formatted = array_map(function ($review) {
            return [
                'id' => (int) $review['id'],
                'reviewer_id' => (int) $review['reviewer_id'],
                'reviewer_name' => $review['reviewer_name'] ?? 'Unknown',
                'reviewer_avatar' => $review['reviewer_avatar'],
                'receiver_id' => (int) $review['receiver_id'],
                'receiver_name' => $review['receiver_name'] ?? 'Unknown',
                'receiver_avatar' => $review['receiver_avatar'],
                'rating' => (int) $review['rating'],
                'comment' => $review['comment'],
                'content' => $review['comment'], // Alias for frontend compatibility
                'status' => $review['status'],
                'is_anonymous' => (bool) ($review['is_anonymous'] ?? false),
                'created_at' => $review['created_at'],
            ];
        }, $reviews);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/reviews/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $query = "SELECT r.*,
                         reviewer.name as reviewer_name,
                         reviewer.email as reviewer_email,
                         reviewer.avatar_url as reviewer_avatar,
                         receiver.name as receiver_name,
                         receiver.email as receiver_email,
                         receiver.avatar_url as receiver_avatar
                  FROM reviews r
                  LEFT JOIN users reviewer ON r.reviewer_id = reviewer.id
                  LEFT JOIN users receiver ON r.receiver_id = receiver.id
                  WHERE r.id = ? AND r.tenant_id = ?";

        $stmt = Database::query($query, [$id, $tenantId]);
        $review = $stmt->fetch();

        if (!$review) {
            $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
            return;
        }

        $formatted = [
            'id' => (int) $review['id'],
            'reviewer_id' => (int) $review['reviewer_id'],
            'reviewer_name' => $review['reviewer_name'] ?? 'Unknown',
            'reviewer_email' => $review['reviewer_email'],
            'reviewer_avatar' => $review['reviewer_avatar'],
            'receiver_id' => (int) $review['receiver_id'],
            'receiver_name' => $review['receiver_name'] ?? 'Unknown',
            'receiver_email' => $review['receiver_email'],
            'receiver_avatar' => $review['receiver_avatar'],
            'rating' => (int) $review['rating'],
            'comment' => $review['comment'],
            'content' => $review['comment'], // Alias for frontend compatibility
            'status' => $review['status'],
            'is_anonymous' => (bool) ($review['is_anonymous'] ?? false),
            'transaction_id' => $review['transaction_id'] ? (int) $review['transaction_id'] : null,
            'group_id' => $review['group_id'] ? (int) $review['group_id'] : null,
            'created_at' => $review['created_at'],
        ];

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/reviews/{id}/flag
     * Flags a review for admin attention by setting status to 'pending'
     */
    public function flag(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify review exists
        $stmt = Database::query(
            "SELECT id, status FROM reviews WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $review = $stmt->fetch();

        if (!$review) {
            $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
            return;
        }

        // Update review status to pending (flagged for review)
        Database::query(
            "UPDATE reviews SET status = 'pending' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'flag_review',
            "Flagged review #{$id} for admin review (set status to pending)"
        );

        $this->respondWithData(['success' => true, 'message' => 'Review flagged for attention']);
    }

    /**
     * POST /api/v2/admin/reviews/{id}/hide
     */
    public function hide(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify review exists
        $stmt = Database::query(
            "SELECT id, status FROM reviews WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $review = $stmt->fetch();

        if (!$review) {
            $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
            return;
        }

        // Update review status to rejected (hidden)
        Database::query(
            "UPDATE reviews SET status = 'rejected' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'hide_review',
            "Hidden review #{$id} (set status to rejected)"
        );

        $this->respondWithData(['success' => true, 'message' => 'Review hidden']);
    }

    /**
     * DELETE /api/v2/admin/reviews/{id}
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify review exists
        $stmt = Database::query(
            "SELECT id FROM reviews WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $review = $stmt->fetch();

        if (!$review) {
            $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
            return;
        }

        // Delete review
        Database::query(
            "DELETE FROM reviews WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'delete_review',
            "Deleted review #{$id}"
        );

        $this->respondWithData(['success' => true, 'message' => 'Review deleted']);
    }
}
