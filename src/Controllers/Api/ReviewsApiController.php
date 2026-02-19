<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ReviewService;
use Nexus\Services\GamificationService;
use Nexus\Services\NotificationDispatcher;
use Nexus\Models\User;

/**
 * ReviewsApiController - RESTful API for user reviews/trust system
 *
 * Provides review management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/reviews/user/{userId}        - Get reviews for a user (cursor paginated)
 * - GET    /api/v2/reviews/user/{userId}/stats  - Get review statistics for a user
 * - GET    /api/v2/reviews/user/{userId}/trust  - Get trust score for a user
 * - GET    /api/v2/reviews/pending              - Get transactions awaiting review
 * - GET    /api/v2/reviews/{id}                 - Get single review
 * - POST   /api/v2/reviews                      - Create a review
 * - DELETE /api/v2/reviews/{id}                 - Delete own review
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class ReviewsApiController extends BaseApiController
{
    /**
     * Validation errors
     */
    private array $errors = [];

    /**
     * GET /api/v2/reviews/user/{userId}
     *
     * Get reviews for a user with cursor-based pagination.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 50)
     *
     * Response: 200 OK with reviews array and pagination meta
     */
    public function userReviews(int $userId): void
    {
        $currentUserId = $this->getUserId();
        $this->rateLimit('reviews_list', 60, 60);

        $limit = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');

        // Decode cursor (it's a base64 encoded offset for simplicity)
        $offset = 0;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $offset = (int)$decoded;
            }
        }

        // Get current user's tenant for privacy filtering
        $currentUserTenant = null;
        if ($currentUserId) {
            $currentUser = User::findById($currentUserId);
            $currentUserTenant = $currentUser['tenant_id'] ?? null;
        }

        // Use the existing ReviewService
        $result = ReviewService::getReviewsForUser($userId, $currentUserTenant, $limit + 1, $offset);

        // Check if hidden
        if (isset($result['reason']) && $result['reason'] === 'reviews_hidden') {
            $this->respondWithData([
                'reviews' => [],
                'hidden' => true,
                'message' => 'This user has hidden their reviews from federated users',
            ]);
            return;
        }

        $reviews = $result['reviews'] ?? [];
        $hasMore = count($reviews) > $limit;

        if ($hasMore) {
            array_pop($reviews);
        }

        // Format reviews
        $formattedReviews = array_map(function ($r) {
            return [
                'id' => (int)$r['id'],
                'rating' => (int)$r['rating'],
                'comment' => $r['comment'],
                'review_type' => $r['review_type'] ?? 'local',
                'is_anonymous' => (bool)($r['is_anonymous'] ?? false),
                'reviewer' => [
                    'name' => $r['reviewer_name'],
                    'avatar_url' => $r['reviewer_avatar'] ?? null,
                    'timebank' => $r['reviewer_timebank'] ?? null,
                ],
                'created_at' => $r['created_at'],
            ];
        }, $reviews);

        $nextCursor = $hasMore ? base64_encode((string)($offset + $limit)) : null;

        $this->respondWithCollection($formattedReviews, $nextCursor, $limit, $hasMore);
    }

    /**
     * GET /api/v2/reviews/user/{userId}/stats
     *
     * Get review statistics for a user.
     *
     * Response: 200 OK with stats object
     */
    public function userStats(int $userId): void
    {
        $currentUserId = $this->getUserId();
        $this->rateLimit('reviews_stats', 120, 60);

        // Get current user's tenant for privacy filtering
        $currentUserTenant = null;
        if ($currentUserId) {
            $currentUser = User::findById($currentUserId);
            $currentUserTenant = $currentUser['tenant_id'] ?? null;
        }

        $result = ReviewService::getReviewsForUser($userId, $currentUserTenant, 1, 0);

        if (isset($result['reason']) && $result['reason'] === 'reviews_hidden') {
            $this->respondWithData([
                'hidden' => true,
                'message' => 'This user has hidden their reviews from federated users',
            ]);
            return;
        }

        $this->respondWithData($result['stats'] ?? [
            'total' => 0,
            'average' => 0,
            'positive' => 0,
            'negative' => 0,
            'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        ]);
    }

    /**
     * GET /api/v2/reviews/user/{userId}/trust
     *
     * Get trust score for a user.
     *
     * Response: 200 OK with trust score object
     */
    public function userTrust(int $userId): void
    {
        $this->getUserId();
        $this->rateLimit('reviews_trust', 120, 60);

        $trustData = ReviewService::calculateTrustScore($userId);

        $this->respondWithData($trustData);
    }

    /**
     * GET /api/v2/reviews/pending
     *
     * Get transactions that the current user can review.
     *
     * Response: 200 OK with pending reviews array
     */
    public function pending(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('reviews_pending', 60, 60);

        $pending = ReviewService::getPendingReviews($userId);

        // Also check local transactions without reviews
        $localPending = $this->getPendingLocalTransactions($userId);

        $this->respondWithData([
            'federated' => $pending,
            'local' => $localPending,
            'total_pending' => count($pending) + count($localPending),
        ]);
    }

    /**
     * Get pending local transactions that need reviews
     */
    private function getPendingLocalTransactions(int $userId): array
    {
        $db = Database::getConnection();

        // Get completed transactions where user hasn't left a review
        $stmt = $db->prepare("
            SELECT
                t.id,
                t.amount,
                t.description,
                t.created_at,
                CASE
                    WHEN t.sender_id = ? THEN 'sent'
                    ELSE 'received'
                END as direction,
                CASE
                    WHEN t.sender_id = ? THEN receiver.name
                    ELSE sender.name
                END as other_party_name,
                CASE
                    WHEN t.sender_id = ? THEN t.receiver_id
                    ELSE t.sender_id
                END as other_party_id
            FROM transactions t
            JOIN users sender ON sender.id = t.sender_id
            JOIN users receiver ON receiver.id = t.receiver_id
            LEFT JOIN reviews r ON (
                r.transaction_id = t.id
                AND r.reviewer_id = ?
            )
            WHERE (t.sender_id = ? OR t.receiver_id = ?)
            AND r.id IS NULL
            AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY t.created_at DESC
            LIMIT 10
        ");

        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * GET /api/v2/reviews/{id}
     *
     * Get a single review by ID.
     *
     * Response: 200 OK with review data
     */
    public function show(int $id): void
    {
        $this->getUserId();
        $this->rateLimit('reviews_show', 120, 60);

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT r.*,
                   reviewer.name as reviewer_name,
                   reviewer.avatar_url as reviewer_avatar,
                   receiver.name as receiver_name,
                   receiver.avatar_url as receiver_avatar
            FROM reviews r
            JOIN users reviewer ON reviewer.id = r.reviewer_id
            JOIN users receiver ON receiver.id = r.receiver_id
            WHERE r.id = ? AND r.status = 'approved'
        ");
        $stmt->execute([$id]);
        $review = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$review) {
            $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
            return;
        }

        $this->respondWithData([
            'id' => (int)$review['id'],
            'rating' => (int)$review['rating'],
            'comment' => $review['comment'],
            'review_type' => $review['review_type'] ?? 'local',
            'is_anonymous' => (bool)($review['is_anonymous'] ?? false),
            'reviewer' => [
                'id' => (int)$review['reviewer_id'],
                'name' => $review['is_anonymous'] ? 'Anonymous' : $review['reviewer_name'],
                'avatar_url' => $review['is_anonymous'] ? null : $review['reviewer_avatar'],
            ],
            'receiver' => [
                'id' => (int)$review['receiver_id'],
                'name' => $review['receiver_name'],
                'avatar_url' => $review['receiver_avatar'],
            ],
            'created_at' => $review['created_at'],
        ]);
    }

    /**
     * POST /api/v2/reviews
     *
     * Create a new review.
     *
     * Request Body (JSON):
     * {
     *   "receiver_id": int (required),
     *   "rating": int 1-5 (required),
     *   "comment": "string" (optional, max 2000 chars),
     *   "transaction_id": int (optional - local transaction),
     *   "federation_transaction_id": int (optional - federated transaction)
     * }
     *
     * Response: 201 Created with review data
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('reviews_create', 10, 60);

        // Validate input
        $receiverId = $this->inputInt('receiver_id');
        $rating = $this->inputInt('rating');
        $comment = trim($this->input('comment', ''));
        $transactionId = $this->inputInt('transaction_id') ?: null;
        $federationTransactionId = $this->inputInt('federation_transaction_id') ?: null;

        // Validation
        if (!$receiverId) {
            $this->respondWithError('VALIDATION_ERROR', 'Receiver ID is required', 'receiver_id', 400);
            return;
        }

        if (!$rating || $rating < 1 || $rating > 5) {
            $this->respondWithError('VALIDATION_ERROR', 'Rating must be between 1 and 5', 'rating', 400);
            return;
        }

        if ($receiverId === $userId) {
            $this->respondWithError('VALIDATION_ERROR', 'Cannot review yourself', 'receiver_id', 400);
            return;
        }

        // Verify receiver exists
        $receiver = User::findById($receiverId);
        if (!$receiver) {
            $this->respondWithError('NOT_FOUND', 'User not found', 'receiver_id', 404);
            return;
        }

        // Truncate comment if too long
        if (strlen($comment) > 2000) {
            $comment = substr($comment, 0, 2000);
        }

        // Use ReviewService for federation transactions
        if ($federationTransactionId) {
            $result = ReviewService::createReview(
                $userId,
                $receiverId,
                $rating,
                $comment ?: null,
                $transactionId,
                $federationTransactionId
            );

            if (!$result['success']) {
                $this->respondWithError('VALIDATION_ERROR', $result['error'], null, 400);
                return;
            }

            $reviewId = $result['review_id'];
        } else {
            // Local review - use direct insert (adapted from Review::create)
            $db = Database::getConnection();

            // Check for existing review on this transaction
            if ($transactionId) {
                $stmt = $db->prepare("SELECT id FROM reviews WHERE transaction_id = ? AND reviewer_id = ?");
                $stmt->execute([$transactionId, $userId]);
                if ($stmt->fetch()) {
                    $this->respondWithError('VALIDATION_ERROR', 'You have already reviewed this transaction', 'transaction_id', 400);
                    return;
                }

                // Validate transaction belongs to these users
                $stmt = $db->prepare("SELECT sender_id, receiver_id FROM transactions WHERE id = ?");
                $stmt->execute([$transactionId]);
                $txn = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$txn) {
                    $this->respondWithError('NOT_FOUND', 'Transaction not found', 'transaction_id', 404);
                    return;
                }

                $isSender = (int)$txn['sender_id'] === $userId;
                $isReceiver = (int)$txn['receiver_id'] === $userId;

                if (!$isSender && !$isReceiver) {
                    $this->respondWithError('FORBIDDEN', 'You are not part of this transaction', 'transaction_id', 403);
                    return;
                }

                // Verify receiver is the other party
                $expectedReceiver = $isSender ? (int)$txn['receiver_id'] : (int)$txn['sender_id'];
                if ($receiverId !== $expectedReceiver) {
                    $this->respondWithError('VALIDATION_ERROR', 'Receiver must be the other party in the transaction', 'receiver_id', 400);
                    return;
                }
            }

            // Get tenant IDs
            $reviewer = User::findById($userId);
            $reviewerTenantId = $reviewer['tenant_id'] ?? TenantContext::getId();
            $receiverTenantId = $receiver['tenant_id'] ?? TenantContext::getId();

            $stmt = $db->prepare("
                INSERT INTO reviews (
                    transaction_id, reviewer_id, reviewer_tenant_id,
                    receiver_id, receiver_tenant_id,
                    rating, comment, review_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'local', 'approved', NOW())
            ");

            $stmt->execute([
                $transactionId,
                $userId,
                $reviewerTenantId,
                $receiverId,
                $receiverTenantId,
                $rating,
                $comment ?: null,
            ]);

            $reviewId = $db->lastInsertId();

            // Log activity
            \Nexus\Models\ActivityLog::log($userId, "left_review", "Rated user $rating/5");
        }

        // Gamification
        try {
            GamificationService::checkReviewBadges($userId, $receiverId, $rating);
        } catch (\Throwable $e) {
            error_log("Gamification review error: " . $e->getMessage());
        }

        // Notification
        $sender = User::findById($userId);
        $content = "You received a new {$rating}-star review from {$sender['first_name']}.";

        try {
            NotificationDispatcher::dispatch(
                $receiverId,
                'global',
                0,
                'new_review',
                $content,
                '/profile?id=' . $receiverId,
                null
            );
        } catch (\Throwable $e) {
            error_log("Review notification error: " . $e->getMessage());
        }

        // Return created review
        $this->respondWithData([
            'id' => (int)$reviewId,
            'rating' => $rating,
            'comment' => $comment ?: null,
            'receiver_id' => $receiverId,
            'message' => 'Review submitted successfully',
        ], null, 201);
    }

    /**
     * DELETE /api/v2/reviews/{id}
     *
     * Delete a review (only own reviews).
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('reviews_delete', 10, 60);

        $db = Database::getConnection();

        // Verify ownership
        $stmt = $db->prepare("SELECT reviewer_id FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        $review = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$review) {
            $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
            return;
        }

        if ((int)$review['reviewer_id'] !== $userId) {
            $this->respondWithError('FORBIDDEN', 'You can only delete your own reviews', null, 403);
            return;
        }

        // Soft delete by setting status to hidden
        $stmt = $db->prepare("UPDATE reviews SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$id]);

        $this->noContent();
    }
}
