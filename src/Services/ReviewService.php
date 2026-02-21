<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ReviewService
 *
 * Handles review operations for both local and federated transactions.
 * Provides methods to create, retrieve, and manage reviews with
 * privacy controls for cross-tenant visibility.
 */
class ReviewService
{
    /**
     * Create a new review
     *
     * @param int $reviewerId User leaving the review
     * @param int $receiverId User being reviewed
     * @param int $rating Rating 1-5
     * @param string|null $comment Optional review text
     * @param int|null $transactionId Local transaction ID
     * @param int|null $federationTransactionId Federation transaction ID
     * @return array Result with success status and review ID or error
     */
    public static function createReview(
        int $reviewerId,
        int $receiverId,
        int $rating,
        ?string $comment = null,
        ?int $transactionId = null,
        ?int $federationTransactionId = null
    ): array {
        try {
            $db = Database::getInstance();

            // Validate rating
            if ($rating < 1 || $rating > 5) {
                return ['success' => false, 'error' => 'Rating must be between 1 and 5'];
            }

            // Get tenant IDs for both users
            $reviewer = Database::query("SELECT tenant_id FROM users WHERE id = ?", [$reviewerId])->fetch(\PDO::FETCH_ASSOC);
            $receiver = Database::query("SELECT tenant_id FROM users WHERE id = ?", [$receiverId])->fetch(\PDO::FETCH_ASSOC);

            if (!$reviewer || !$receiver) {
                return ['success' => false, 'error' => 'Invalid user'];
            }

            $reviewerTenantId = $reviewer['tenant_id'];
            $receiverTenantId = $receiver['tenant_id'];

            // Determine review type
            $reviewType = ($reviewerTenantId === $receiverTenantId) ? 'local' : 'federated';

            // Check if review already exists for this transaction
            if ($transactionId) {
                $existing = Database::query(
                    "SELECT id FROM reviews WHERE transaction_id = ? AND reviewer_id = ?",
                    [$transactionId, $reviewerId]
                )->fetch();
                if ($existing) {
                    return ['success' => false, 'error' => 'You have already reviewed this exchange'];
                }
            }

            if ($federationTransactionId) {
                $existing = Database::query(
                    "SELECT id FROM reviews WHERE federation_transaction_id = ? AND reviewer_id = ?",
                    [$federationTransactionId, $reviewerId]
                )->fetch();
                if ($existing) {
                    return ['success' => false, 'error' => 'You have already reviewed this exchange'];
                }
            }

            // Validate transaction belongs to these users
            if ($federationTransactionId) {
                $transaction = Database::query(
                    "SELECT * FROM federation_transactions WHERE id = ?",
                    [$federationTransactionId]
                )->fetch(\PDO::FETCH_ASSOC);

                if (!$transaction) {
                    return ['success' => false, 'error' => 'Transaction not found'];
                }

                // Reviewer must be sender or receiver
                $isSender = ((int)$transaction['sender_user_id'] === $reviewerId);
                $isReceiver = ((int)$transaction['receiver_user_id'] === $reviewerId);

                if (!$isSender && !$isReceiver) {
                    return ['success' => false, 'error' => 'You are not part of this transaction'];
                }

                // Receiver of review must be the other party
                if ($isSender && (int)$transaction['receiver_user_id'] !== $receiverId) {
                    return ['success' => false, 'error' => 'Invalid receiver for this transaction'];
                }
                if ($isReceiver && (int)$transaction['sender_user_id'] !== $receiverId) {
                    return ['success' => false, 'error' => 'Invalid receiver for this transaction'];
                }

                // Transaction must be completed
                if ($transaction['status'] !== 'completed') {
                    return ['success' => false, 'error' => 'Can only review completed transactions'];
                }
            }

            // Sanitize comment
            $comment = $comment ? trim(strip_tags($comment)) : null;
            if ($comment && strlen($comment) > 2000) {
                $comment = substr($comment, 0, 2000);
            }

            // Insert review
            $stmt = $db->prepare("
                INSERT INTO reviews (
                    transaction_id, federation_transaction_id,
                    reviewer_id, reviewer_tenant_id,
                    receiver_id, receiver_tenant_id,
                    rating, comment, review_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
            ");

            $stmt->execute([
                $transactionId,
                $federationTransactionId,
                $reviewerId,
                $reviewerTenantId,
                $receiverId,
                $receiverTenantId,
                $rating,
                $comment,
                $reviewType
            ]);

            $reviewId = $db->lastInsertId();

            // Mark transaction as reviewed
            if ($federationTransactionId) {
                $isSender = ((int)$transaction['sender_user_id'] === $reviewerId);
                if ($isSender) {
                    Database::query(
                        "UPDATE federation_transactions SET sender_reviewed = 1 WHERE id = ?",
                        [$federationTransactionId]
                    );
                } else {
                    Database::query(
                        "UPDATE federation_transactions SET receiver_reviewed = 1 WHERE id = ?",
                        [$federationTransactionId]
                    );
                }
            }

            // Log to audit
            if (class_exists(FederationAuditService::class)) {
                FederationAuditService::log(
                    'review_created',
                    $reviewerTenantId,
                    $reviewerId,
                    "Review created for user {$receiverId}, rating: {$rating}",
                    ['review_id' => $reviewId, 'review_type' => $reviewType]
                );
            }

            return [
                'success' => true,
                'review_id' => $reviewId,
                'message' => 'Review submitted successfully'
            ];

        } catch (\Exception $e) {
            error_log("ReviewService::createReview error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create review'];
        }
    }

    /**
     * Get reviews for a user
     *
     * @param int $userId User to get reviews for
     * @param int|null $viewerTenantId Tenant viewing (for privacy filtering)
     * @param int $limit Maximum reviews to return
     * @param int $offset Pagination offset
     * @return array Reviews and statistics
     */
    public static function getReviewsForUser(int $userId, ?int $viewerTenantId = null, int $limit = 10, int $offset = 0): array
    {
        try {
            $db = Database::getInstance();

            // Get user info
            $user = Database::query("SELECT tenant_id FROM users WHERE id = ?", [$userId])->fetch(\PDO::FETCH_ASSOC);
            if (!$user) {
                return ['reviews' => [], 'stats' => null];
            }

            $userTenantId = $user['tenant_id'];
            $isSameTenant = ($viewerTenantId === $userTenantId);

            // Check federation settings if cross-tenant
            if (!$isSameTenant && $viewerTenantId) {
                $settings = Database::query(
                    "SELECT show_reviews_federated FROM federation_user_settings WHERE user_id = ?",
                    [$userId]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($settings && !$settings['show_reviews_federated']) {
                    return ['reviews' => [], 'stats' => null, 'reason' => 'reviews_hidden'];
                }
            }

            // Build query with privacy controls
            $reviewQuery = "
                SELECT
                    r.id,
                    r.rating,
                    r.comment,
                    r.review_type,
                    r.is_anonymous,
                    r.created_at,
                    CASE
                        WHEN r.is_anonymous = 1 THEN 'Anonymous'
                        WHEN reviewer.tenant_id = ? THEN reviewer.name
                        ELSE CONCAT(LEFT(reviewer.name, 1), '***')
                    END as reviewer_name,
                    CASE
                        WHEN r.is_anonymous = 1 THEN NULL
                        WHEN reviewer.tenant_id = ? THEN reviewer.avatar_url
                        ELSE NULL
                    END as reviewer_avatar,
                    reviewer.tenant_id as reviewer_tenant_id,
                    t.name as reviewer_timebank
                FROM reviews r
                JOIN users reviewer ON reviewer.id = r.reviewer_id
                LEFT JOIN tenants t ON t.id = reviewer.tenant_id
                WHERE r.receiver_id = ?
                AND r.status = 'approved'
            ";

            // If cross-tenant, only show reviews marked for cross-tenant visibility
            if (!$isSameTenant) {
                $reviewQuery .= " AND r.show_cross_tenant = 1";
            }

            $reviewQuery .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";

            $reviews = Database::query($reviewQuery, [
                $viewerTenantId ?? 0,
                $viewerTenantId ?? 0,
                $userId,
                $limit,
                $offset
            ])->fetchAll(\PDO::FETCH_ASSOC);

            // Get statistics
            $statsQuery = "
                SELECT
                    COUNT(*) as total,
                    COALESCE(AVG(rating), 0) as average,
                    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive,
                    COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative,
                    COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
                    COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
                    COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
                    COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
                    COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
                FROM reviews
                WHERE receiver_id = ? AND status = 'approved'
            ";

            if (!$isSameTenant) {
                $statsQuery = str_replace(
                    "AND status = 'approved'",
                    "AND status = 'approved' AND show_cross_tenant = 1",
                    $statsQuery
                );
            }

            $stats = Database::query($statsQuery, [$userId])->fetch(\PDO::FETCH_ASSOC);

            return [
                'reviews' => $reviews,
                'stats' => [
                    'total' => (int)$stats['total'],
                    'average' => round((float)$stats['average'], 1),
                    'positive' => (int)$stats['positive'],
                    'negative' => (int)$stats['negative'],
                    'distribution' => [
                        5 => (int)$stats['five_star'],
                        4 => (int)$stats['four_star'],
                        3 => (int)$stats['three_star'],
                        2 => (int)$stats['two_star'],
                        1 => (int)$stats['one_star']
                    ]
                ]
            ];

        } catch (\Exception $e) {
            error_log("ReviewService::getReviewsForUser error: " . $e->getMessage());
            return ['reviews' => [], 'stats' => null];
        }
    }

    /**
     * Check if a user can review a transaction
     *
     * @param int $userId User wanting to leave review
     * @param int $federationTransactionId Transaction ID
     * @return array Eligibility status and reason
     */
    public static function canReviewTransaction(int $userId, int $federationTransactionId): array
    {
        try {
            // Get transaction
            $transaction = Database::query(
                "SELECT * FROM federation_transactions WHERE id = ?",
                [$federationTransactionId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$transaction) {
                return ['can_review' => false, 'reason' => 'Transaction not found'];
            }

            // Check if user is part of transaction
            $isSender = ((int)$transaction['sender_user_id'] === $userId);
            $isReceiver = ((int)$transaction['receiver_user_id'] === $userId);

            if (!$isSender && !$isReceiver) {
                return ['can_review' => false, 'reason' => 'You are not part of this transaction'];
            }

            // Check transaction status
            if ($transaction['status'] !== 'completed') {
                return ['can_review' => false, 'reason' => 'Transaction must be completed to leave a review'];
            }

            // Check if already reviewed
            $reviewed = $isSender ? $transaction['sender_reviewed'] : $transaction['receiver_reviewed'];
            if ($reviewed) {
                return ['can_review' => false, 'reason' => 'You have already reviewed this transaction'];
            }

            // Determine who to review
            $receiverId = $isSender ? $transaction['receiver_user_id'] : $transaction['sender_user_id'];

            return [
                'can_review' => true,
                'receiver_id' => $receiverId,
                'transaction' => $transaction
            ];

        } catch (\Exception $e) {
            error_log("ReviewService::canReviewTransaction error: " . $e->getMessage());
            return ['can_review' => false, 'reason' => 'Error checking review eligibility'];
        }
    }

    /**
     * Get pending reviews for a user (transactions they haven't reviewed yet)
     *
     * @param int $userId User ID
     * @return array List of transactions awaiting review
     */
    public static function getPendingReviews(int $userId): array
    {
        try {
            // Get completed transactions where user hasn't left a review
            $transactions = Database::query("
                SELECT
                    ft.id,
                    ft.amount,
                    ft.description,
                    ft.completed_at,
                    CASE
                        WHEN ft.sender_user_id = ? THEN 'sent'
                        ELSE 'received'
                    END as direction,
                    CASE
                        WHEN ft.sender_user_id = ? THEN receiver.name
                        ELSE sender.name
                    END as other_party_name,
                    CASE
                        WHEN ft.sender_user_id = ? THEN ft.receiver_user_id
                        ELSE ft.sender_user_id
                    END as other_party_id,
                    CASE
                        WHEN ft.sender_user_id = ? THEN receiver_tenant.name
                        ELSE sender_tenant.name
                    END as other_party_timebank
                FROM federation_transactions ft
                JOIN users sender ON sender.id = ft.sender_user_id
                JOIN users receiver ON receiver.id = ft.receiver_user_id
                LEFT JOIN tenants sender_tenant ON sender_tenant.id = ft.sender_tenant_id
                LEFT JOIN tenants receiver_tenant ON receiver_tenant.id = ft.receiver_tenant_id
                WHERE ft.status = 'completed'
                AND (
                    (ft.sender_user_id = ? AND ft.sender_reviewed = 0)
                    OR
                    (ft.receiver_user_id = ? AND ft.receiver_reviewed = 0)
                )
                ORDER BY ft.completed_at DESC
                LIMIT 20
            ", [$userId, $userId, $userId, $userId, $userId, $userId])->fetchAll(\PDO::FETCH_ASSOC);

            return $transactions;

        } catch (\Exception $e) {
            error_log("ReviewService::getPendingReviews error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate trust score for a user based on reviews and activity
     *
     * @param int $userId User ID
     * @return array Trust score and level
     */
    public static function calculateTrustScore(int $userId): array
    {
        try {
            $db = Database::getInstance();

            // Get review stats
            $reviewStats = Database::query("
                SELECT
                    COUNT(*) as review_count,
                    COALESCE(AVG(rating), 0) as avg_rating,
                    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_count
                FROM reviews
                WHERE receiver_id = ? AND status = 'approved'
            ", [$userId])->fetch(\PDO::FETCH_ASSOC);

            // Get transaction stats
            $transactionStats = Database::query("
                SELECT COUNT(*) as transaction_count
                FROM federation_transactions
                WHERE (sender_user_id = ? OR receiver_user_id = ?)
                AND status = 'completed'
            ", [$userId, $userId])->fetch(\PDO::FETCH_ASSOC);

            // Calculate score components
            $reviewCount = (int)$reviewStats['review_count'];
            $avgRating = (float)$reviewStats['avg_rating'];
            $transactionCount = (int)$transactionStats['transaction_count'];

            // Score formula:
            // - Base: 20 points for having an account
            // - Reviews: up to 40 points (based on avg rating * 8)
            // - Volume: up to 20 points (1 point per review, max 20)
            // - Activity: up to 20 points (1 point per 2 transactions, max 20)

            $baseScore = 20;
            $reviewScore = min(40, $avgRating * 8);
            $volumeScore = min(20, $reviewCount);
            $activityScore = min(20, floor($transactionCount / 2));

            $totalScore = round($baseScore + $reviewScore + $volumeScore + $activityScore);
            $totalScore = max(0, min(100, $totalScore)); // Clamp to 0-100

            // Determine level
            $level = 'new';
            if ($totalScore >= 80) {
                $level = 'excellent';
            } elseif ($totalScore >= 60) {
                $level = 'trusted';
            } elseif ($totalScore >= 40) {
                $level = 'established';
            } elseif ($totalScore >= 25) {
                $level = 'growing';
            }

            return [
                'score' => $totalScore,
                'level' => $level,
                'details' => [
                    'review_count' => $reviewCount,
                    'average_rating' => round($avgRating, 1),
                    'transaction_count' => $transactionCount,
                    'cross_tenant_activity' => $transactionCount > 0
                ]
            ];

        } catch (\Exception $e) {
            error_log("ReviewService::calculateTrustScore error: " . $e->getMessage());
            return ['score' => 0, 'level' => 'new', 'details' => []];
        }
    }
}
