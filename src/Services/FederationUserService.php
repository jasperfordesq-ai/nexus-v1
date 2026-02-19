<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;

/**
 * Federation User Service
 *
 * Manages individual user federation settings and preferences.
 * Users must explicitly opt-in to federation features.
 */
class FederationUserService
{
    /**
     * Get user's federation settings
     */
    public static function getUserSettings(int $userId): array
    {
        try {
            $settings = Database::query(
                "SELECT * FROM federation_user_settings WHERE user_id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$settings) {
                // Return defaults - all OFF
                return [
                    'user_id' => $userId,
                    'federation_optin' => false,
                    'profile_visible_federated' => false,
                    'messaging_enabled_federated' => false,
                    'transactions_enabled_federated' => false,
                    'appear_in_federated_search' => false,
                    'show_skills_federated' => false,
                    'show_location_federated' => false,
                    'service_reach' => 'local_only',
                    'travel_radius_km' => null,
                ];
            }

            // Convert tinyint to bool for easier use
            return [
                'user_id' => $settings['user_id'],
                'federation_optin' => (bool)$settings['federation_optin'],
                'profile_visible_federated' => (bool)$settings['profile_visible_federated'],
                'messaging_enabled_federated' => (bool)$settings['messaging_enabled_federated'],
                'transactions_enabled_federated' => (bool)$settings['transactions_enabled_federated'],
                'appear_in_federated_search' => (bool)$settings['appear_in_federated_search'],
                'show_skills_federated' => (bool)$settings['show_skills_federated'],
                'show_location_federated' => (bool)$settings['show_location_federated'],
                'service_reach' => $settings['service_reach'] ?? 'local_only',
                'travel_radius_km' => $settings['travel_radius_km'],
                'opted_in_at' => $settings['opted_in_at'],
            ];

        } catch (\Exception $e) {
            error_log("FederationUserService::getUserSettings error: " . $e->getMessage());
            return [
                'user_id' => $userId,
                'federation_optin' => false,
                'profile_visible_federated' => false,
                'messaging_enabled_federated' => false,
                'transactions_enabled_federated' => false,
                'appear_in_federated_search' => false,
                'show_skills_federated' => false,
                'show_location_federated' => false,
                'service_reach' => 'local_only',
                'travel_radius_km' => null,
            ];
        }
    }

    /**
     * Update user's federation settings
     */
    public static function updateSettings(int $userId, array $settings): bool
    {
        try {
            // Check if record exists
            $exists = Database::query(
                "SELECT user_id FROM federation_user_settings WHERE user_id = ?",
                [$userId]
            )->fetch();

            // Prepare values
            $federationOptin = (int)($settings['federation_optin'] ?? false);
            $profileVisible = (int)($settings['profile_visible_federated'] ?? false);
            $messagingEnabled = (int)($settings['messaging_enabled_federated'] ?? false);
            $transactionsEnabled = (int)($settings['transactions_enabled_federated'] ?? false);
            $appearInSearch = (int)($settings['appear_in_federated_search'] ?? false);
            $showSkills = (int)($settings['show_skills_federated'] ?? false);
            $showLocation = (int)($settings['show_location_federated'] ?? false);
            $serviceReach = $settings['service_reach'] ?? 'local_only';
            $travelRadius = isset($settings['travel_radius_km']) && $settings['travel_radius_km'] !== ''
                ? (int)$settings['travel_radius_km']
                : null;

            // Validate service_reach
            $validReach = ['local_only', 'remote_ok', 'travel_ok'];
            if (!in_array($serviceReach, $validReach)) {
                $serviceReach = 'local_only';
            }

            if ($exists) {
                // Update
                $sql = "UPDATE federation_user_settings SET
                    federation_optin = ?,
                    profile_visible_federated = ?,
                    messaging_enabled_federated = ?,
                    transactions_enabled_federated = ?,
                    appear_in_federated_search = ?,
                    show_skills_federated = ?,
                    show_location_federated = ?,
                    service_reach = ?,
                    travel_radius_km = ?,
                    opted_in_at = CASE WHEN ? = 1 AND opted_in_at IS NULL THEN NOW() ELSE opted_in_at END,
                    updated_at = NOW()
                    WHERE user_id = ?";

                Database::query($sql, [
                    $federationOptin,
                    $profileVisible,
                    $messagingEnabled,
                    $transactionsEnabled,
                    $appearInSearch,
                    $showSkills,
                    $showLocation,
                    $serviceReach,
                    $travelRadius,
                    $federationOptin,
                    $userId
                ]);
            } else {
                // Insert
                $sql = "INSERT INTO federation_user_settings (
                    user_id, federation_optin, profile_visible_federated,
                    messaging_enabled_federated, transactions_enabled_federated,
                    appear_in_federated_search, show_skills_federated,
                    show_location_federated, service_reach, travel_radius_km,
                    opted_in_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                Database::query($sql, [
                    $userId,
                    $federationOptin,
                    $profileVisible,
                    $messagingEnabled,
                    $transactionsEnabled,
                    $appearInSearch,
                    $showSkills,
                    $showLocation,
                    $serviceReach,
                    $travelRadius,
                    $federationOptin ? date('Y-m-d H:i:s') : null
                ]);
            }

            // Log the change
            FederationAuditService::log(
                'user_settings_updated',
                null,
                null,
                $userId,
                ['settings' => $settings],
                FederationAuditService::LEVEL_INFO
            );

            return true;

        } catch (\Exception $e) {
            error_log("FederationUserService::updateSettings error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has opted into federation
     */
    public static function hasOptedIn(int $userId): bool
    {
        try {
            $result = Database::query(
                "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            return (bool)($result['federation_optin'] ?? false);

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Quick opt-out - disable all federation for user
     */
    public static function optOut(int $userId): bool
    {
        return self::updateSettings($userId, [
            'federation_optin' => false,
            'profile_visible_federated' => false,
            'messaging_enabled_federated' => false,
            'transactions_enabled_federated' => false,
            'appear_in_federated_search' => false,
            'show_skills_federated' => false,
            'show_location_federated' => false,
        ]);
    }

    /**
     * Get users who are visible in federated search for a tenant
     */
    public static function getFederatedUsers(int $tenantId, array $filters = []): array
    {
        try {
            $sql = "SELECT u.id, u.name, u.avatar_url, u.bio, u.location, u.skills,
                           fus.service_reach, fus.travel_radius_km, fus.show_skills_federated,
                           fus.show_location_federated
                    FROM users u
                    INNER JOIN federation_user_settings fus ON u.id = fus.user_id
                    WHERE u.tenant_id = ?
                    AND u.status = 'active'
                    AND fus.federation_optin = 1
                    AND fus.appear_in_federated_search = 1";

            $params = [$tenantId];

            // Apply filters
            if (!empty($filters['service_reach'])) {
                if ($filters['service_reach'] === 'remote_ok') {
                    $sql .= " AND fus.service_reach IN ('remote_ok', 'travel_ok')";
                } elseif ($filters['service_reach'] === 'travel_ok') {
                    $sql .= " AND fus.service_reach = 'travel_ok'";
                }
            }

            $sql .= " ORDER BY u.name LIMIT 100";

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationUserService::getFederatedUsers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if federation is available for user's tenant
     */
    public static function isFederationAvailableForUser(int $userId): bool
    {
        try {
            // Get user's tenant
            $user = Database::query(
                "SELECT tenant_id FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Check if federation is enabled globally and for tenant
            return FederationFeatureService::isGloballyEnabled()
                && FederationFeatureService::isTenantWhitelisted($user['tenant_id'])
                && FederationFeatureService::isTenantFederationEnabled($user['tenant_id']);

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get reviews for a federated user
     * Only returns reviews if the user has opted in to show reviews cross-tenant
     *
     * @param int $userId The user being viewed
     * @param int $viewerTenantId The tenant viewing the profile
     * @param int $limit Maximum number of reviews to return
     * @return array Reviews with reviewer info and stats
     */
    public static function getFederatedReviews(int $userId, int $viewerTenantId, int $limit = 5): array
    {
        try {
            // Get user's tenant
            $user = Database::query(
                "SELECT tenant_id FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                return ['reviews' => [], 'stats' => null];
            }

            $userTenantId = $user['tenant_id'];

            // Check if there's an active partnership between tenants
            $partnership = FederationPartnershipService::getPartnership($viewerTenantId, $userTenantId);
            if (!$partnership || $partnership['status'] !== 'active') {
                return ['reviews' => [], 'stats' => null, 'reason' => 'no_partnership'];
            }

            // Check partnership level - need at least level 2 (Social) for profiles
            if (($partnership['federation_level'] ?? 1) < 2) {
                return ['reviews' => [], 'stats' => null, 'reason' => 'insufficient_level'];
            }

            // Get review statistics
            $stats = Database::query("
                SELECT
                    COUNT(*) as total_reviews,
                    AVG(rating) as average_rating,
                    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_reviews,
                    COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_reviews
                FROM reviews
                WHERE receiver_id = ? AND status = 'approved'
            ", [$userId])->fetch(\PDO::FETCH_ASSOC);

            // Get recent reviews with reviewer info (anonymized for privacy)
            $reviews = Database::query("
                SELECT
                    r.id,
                    r.rating,
                    r.comment,
                    r.created_at,
                    r.transaction_id,
                    CASE
                        WHEN reviewer.tenant_id = ? THEN reviewer.name
                        ELSE CONCAT(LEFT(reviewer.name, 1), '***')
                    END as reviewer_name,
                    CASE
                        WHEN reviewer.tenant_id = ? THEN reviewer.avatar_url
                        ELSE NULL
                    END as reviewer_avatar,
                    reviewer.tenant_id as reviewer_tenant_id,
                    t.name as reviewer_timebank,
                    CASE
                        WHEN reviewer.tenant_id = ? THEN 0
                        ELSE 1
                    END as is_cross_tenant
                FROM reviews r
                INNER JOIN users reviewer ON r.reviewer_id = reviewer.id
                LEFT JOIN tenants t ON reviewer.tenant_id = t.id
                WHERE r.receiver_id = ?
                AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT ?
            ", [$viewerTenantId, $viewerTenantId, $viewerTenantId, $userId, $limit])->fetchAll(\PDO::FETCH_ASSOC);

            // Format the reviews
            $formattedReviews = array_map(function($review) {
                return [
                    'id' => $review['id'],
                    'rating' => (int)$review['rating'],
                    'comment' => $review['comment'],
                    'created_at' => $review['created_at'],
                    'time_ago' => self::timeAgo($review['created_at']),
                    'reviewer_name' => $review['reviewer_name'],
                    'reviewer_avatar' => $review['reviewer_avatar'],
                    'reviewer_timebank' => $review['reviewer_timebank'],
                    'is_cross_tenant' => (bool)$review['is_cross_tenant'],
                    'has_transaction' => !empty($review['transaction_id']),
                ];
            }, $reviews);

            return [
                'reviews' => $formattedReviews,
                'stats' => [
                    'total' => (int)($stats['total_reviews'] ?? 0),
                    'average' => round((float)($stats['average_rating'] ?? 0), 1),
                    'positive' => (int)($stats['positive_reviews'] ?? 0),
                    'negative' => (int)($stats['negative_reviews'] ?? 0),
                ],
            ];

        } catch (\Exception $e) {
            error_log("FederationUserService::getFederatedReviews error: " . $e->getMessage());
            return ['reviews' => [], 'stats' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get trust score for a federated user
     * Combines review data with transaction history
     *
     * @param int $userId The user to get trust score for
     * @return array Trust score components
     */
    public static function getTrustScore(int $userId): array
    {
        try {
            // Get review stats
            $reviewStats = Database::query("
                SELECT
                    COUNT(*) as review_count,
                    AVG(rating) as avg_rating
                FROM reviews
                WHERE receiver_id = ? AND status = 'approved'
            ", [$userId])->fetch(\PDO::FETCH_ASSOC);

            // Get transaction stats
            $txStats = Database::query("
                SELECT
                    COUNT(*) as transaction_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
                FROM transactions
                WHERE (sender_id = ? OR receiver_id = ?)
            ", [$userId, $userId])->fetch(\PDO::FETCH_ASSOC);

            // Get federation activity
            $fedActivity = Database::query("
                SELECT COUNT(*) as cross_tenant_count
                FROM federation_audit_log
                WHERE actor_user_id = ?
                AND action_type IN ('cross_tenant_transaction', 'cross_tenant_message')
                AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
            ", [$userId])->fetch(\PDO::FETCH_ASSOC);

            // Calculate trust score (0-100)
            $reviewScore = 0;
            $transactionScore = 0;
            $federationBonus = 0;

            // Review component (up to 40 points)
            $reviewCount = (int)($reviewStats['review_count'] ?? 0);
            $avgRating = (float)($reviewStats['avg_rating'] ?? 0);
            if ($reviewCount > 0) {
                $ratingScore = ($avgRating / 5) * 30; // Up to 30 points for rating
                $volumeScore = min($reviewCount / 10, 1) * 10; // Up to 10 points for volume
                $reviewScore = $ratingScore + $volumeScore;
            }

            // Transaction component (up to 40 points)
            $txCount = (int)($txStats['transaction_count'] ?? 0);
            $completedCount = (int)($txStats['completed_count'] ?? 0);
            if ($txCount > 0) {
                $completionRate = $completedCount / $txCount;
                $rateScore = $completionRate * 25; // Up to 25 points for completion rate
                $txVolumeScore = min($txCount / 20, 1) * 15; // Up to 15 points for volume
                $transactionScore = $rateScore + $txVolumeScore;
            }

            // Federation bonus (up to 20 points)
            $crossTenantCount = (int)($fedActivity['cross_tenant_count'] ?? 0);
            $federationBonus = min($crossTenantCount / 5, 1) * 20;

            $totalScore = round($reviewScore + $transactionScore + $federationBonus);

            // Determine trust level
            $trustLevel = 'new';
            if ($totalScore >= 80) $trustLevel = 'excellent';
            elseif ($totalScore >= 60) $trustLevel = 'trusted';
            elseif ($totalScore >= 40) $trustLevel = 'established';
            elseif ($totalScore >= 20) $trustLevel = 'growing';

            return [
                'score' => $totalScore,
                'level' => $trustLevel,
                'components' => [
                    'reviews' => round($reviewScore),
                    'transactions' => round($transactionScore),
                    'federation' => round($federationBonus),
                ],
                'details' => [
                    'review_count' => $reviewCount,
                    'avg_rating' => $avgRating,
                    'transaction_count' => $txCount,
                    'completion_rate' => $txCount > 0 ? round(($completedCount / $txCount) * 100) : 0,
                    'cross_tenant_activity' => $crossTenantCount,
                ],
            ];

        } catch (\Exception $e) {
            error_log("FederationUserService::getTrustScore error: " . $e->getMessage());
            return [
                'score' => 0,
                'level' => 'unknown',
                'components' => ['reviews' => 0, 'transactions' => 0, 'federation' => 0],
                'details' => [],
            ];
        }
    }

    /**
     * Helper to format time ago
     */
    private static function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
        if ($diff < 31536000) return floor($diff / 2592000) . 'mo ago';
        return floor($diff / 31536000) . 'y ago';
    }
}
