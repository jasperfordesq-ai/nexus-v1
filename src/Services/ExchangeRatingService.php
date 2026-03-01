<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ExchangeRatingService (W10)
 *
 * Manages post-exchange satisfaction ratings.
 * After an exchange completes, both the requester and provider
 * can rate each other (1-5 stars + optional comment).
 */
class ExchangeRatingService
{
    /**
     * Submit a rating for a completed exchange
     *
     * @param int $exchangeId Exchange ID
     * @param int $raterId User giving the rating
     * @param int $rating 1-5 star rating
     * @param string|null $comment Optional comment
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function submitRating(int $exchangeId, int $raterId, int $rating, ?string $comment = null): array
    {
        $tenantId = TenantContext::getId();

        // Validate rating range
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'error' => 'Rating must be between 1 and 5'];
        }

        // Get exchange and verify it's completed
        $stmt = Database::query(
            "SELECT * FROM exchange_requests WHERE id = ? AND tenant_id = ? AND status = 'completed'",
            [$exchangeId, $tenantId]
        );
        $exchange = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$exchange) {
            return ['success' => false, 'error' => 'Exchange not found or not completed'];
        }

        // Determine role and rated user
        $isRequester = (int) $exchange['requester_id'] === $raterId;
        $isProvider = (int) $exchange['provider_id'] === $raterId;

        if (!$isRequester && !$isProvider) {
            return ['success' => false, 'error' => 'You are not part of this exchange'];
        }

        $role = $isRequester ? 'requester' : 'provider';
        $ratedId = $isRequester ? (int) $exchange['provider_id'] : (int) $exchange['requester_id'];

        // Check if already rated
        $existingStmt = Database::query(
            "SELECT id FROM exchange_ratings WHERE exchange_id = ? AND rater_id = ?",
            [$exchangeId, $raterId]
        );

        if ($existingStmt->fetch()) {
            return ['success' => false, 'error' => 'You have already rated this exchange'];
        }

        try {
            Database::query(
                "INSERT INTO exchange_ratings
                 (tenant_id, exchange_id, rater_id, rated_id, rating, comment, role)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $exchangeId, $raterId, $ratedId, $rating, $comment, $role]
            );

            // Notify the rated user
            NotificationDispatcher::send($ratedId, 'exchange_rated', [
                'exchange_id' => $exchangeId,
                'rating' => $rating,
                'rater_role' => $role,
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            error_log("ExchangeRatingService::submitRating error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to submit rating'];
        }
    }

    /**
     * Get ratings for an exchange
     *
     * @param int $exchangeId Exchange ID
     * @return array Ratings
     */
    public static function getRatingsForExchange(int $exchangeId): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT er.*,
                    rater.name as rater_name, rater.avatar_url as rater_avatar,
                    rated.name as rated_name, rated.avatar_url as rated_avatar
             FROM exchange_ratings er
             JOIN users rater ON er.rater_id = rater.id
             JOIN users rated ON er.rated_id = rated.id
             WHERE er.exchange_id = ? AND er.tenant_id = ?",
            [$exchangeId, $tenantId]
        );

        $ratings = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($r) {
            return [
                'id' => (int) $r['id'],
                'rater' => [
                    'id' => (int) $r['rater_id'],
                    'name' => $r['rater_name'],
                    'avatar_url' => $r['rater_avatar'],
                ],
                'rated' => [
                    'id' => (int) $r['rated_id'],
                    'name' => $r['rated_name'],
                    'avatar_url' => $r['rated_avatar'],
                ],
                'rating' => (int) $r['rating'],
                'comment' => $r['comment'],
                'role' => $r['role'],
                'created_at' => $r['created_at'],
            ];
        }, $ratings);
    }

    /**
     * Get average rating for a user
     *
     * @param int $userId User ID
     * @return array ['average' => float, 'count' => int]
     */
    public static function getUserRating(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT AVG(rating) as average, COUNT(*) as count
             FROM exchange_ratings
             WHERE rated_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'average' => $result['average'] ? round((float) $result['average'], 1) : null,
            'count' => (int) ($result['count'] ?? 0),
        ];
    }

    /**
     * Check if a user has already rated an exchange
     *
     * @param int $exchangeId Exchange ID
     * @param int $userId User ID
     * @return bool
     */
    public static function hasRated(int $exchangeId, int $userId): bool
    {
        $stmt = Database::query(
            "SELECT 1 FROM exchange_ratings WHERE exchange_id = ? AND rater_id = ?",
            [$exchangeId, $userId]
        );

        return (bool) $stmt->fetch();
    }
}
