<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * MatchNotificationService - Real-Time Match Push Notifications
 *
 * When a new listing (or job) is created, this service checks for users who
 * are a good match and sends them real-time push notifications via Pusher
 * and in-app notifications.
 *
 * This complements the existing SmartMatchingEngine digest-style notifications
 * (which run periodically). This service triggers immediately on listing creation
 * for instant notification delivery.
 *
 * Flow:
 *   1. User creates a listing (e.g., "I need help with gardening")
 *   2. ListingService::create() calls MatchNotificationService::onListingCreated()
 *   3. This service finds users with complementary listings (e.g., "I offer gardening")
 *   4. Matching users receive real-time push + in-app notification
 *
 * Deduplication: The `match_notification_sent` table prevents repeat
 * notifications for the same listing/user pair.
 */
class MatchNotificationService
{
    /**
     * Minimum match score to trigger a real-time notification.
     * Higher than digest threshold since push notifications should be high-value.
     */
    private const MIN_PUSH_SCORE = 65;

    /**
     * Maximum number of users to notify per new listing.
     * Prevents notification storms for very popular categories.
     */
    private const MAX_NOTIFICATIONS_PER_LISTING = 10;

    /**
     * Called when a new listing is created. Finds matching users and
     * sends real-time notifications.
     *
     * This method is designed to be called synchronously from
     * ListingService::create() — it's fast enough for inline execution
     * because it uses a focused query rather than the full matching engine.
     *
     * @param int $listingId The newly created listing ID
     * @param int $creatorUserId The user who created the listing
     * @param array $listingData Listing data (title, type, category_id, etc.)
     * @return int Number of notifications sent
     */
    public static function onListingCreated(int $listingId, int $creatorUserId, array $listingData): int
    {
        $tenantId = TenantContext::getId();

        // Feature gate: check if smart matching is enabled
        try {
            $config = SmartMatchingEngine::getConfig();
            if (empty($config['enabled'])) {
                return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }

        $listingType = $listingData['type'] ?? 'offer';
        $categoryId = $listingData['category_id'] ?? null;
        $listingTitle = $listingData['title'] ?? 'New Listing';

        // Find users with complementary listings in the same or related category
        $matchedUsers = self::findMatchingUsers(
            $tenantId,
            $listingId,
            $creatorUserId,
            $listingType,
            $categoryId
        );

        $notified = 0;

        foreach ($matchedUsers as $match) {
            $matchedUserId = (int)$match['user_id'];

            // Check if already notified for this listing
            if (self::wasAlreadyNotified($tenantId, $listingId, $matchedUserId)) {
                continue;
            }

            // Check user's match notification preferences
            try {
                $prefs = MatchingService::getPreferences($matchedUserId);
                if (empty($prefs['notify_hot_matches']) && empty($prefs['notify_mutual_matches'])) {
                    continue; // User has disabled match notifications
                }
                if (($prefs['notification_frequency'] ?? 'fortnightly') === 'never') {
                    continue;
                }
            } catch (\Exception $e) {
                // If preferences can't be loaded, send notification by default
            }

            // Check if broker approval is required
            $requiresApproval = SmartMatchingEngine::isBrokerApprovalEnabled();

            if ($requiresApproval) {
                // Submit to broker approval queue instead of direct notification
                try {
                    $matchData = [
                        'match_score' => (int)($match['match_score'] ?? self::MIN_PUSH_SCORE),
                        'match_type' => 'realtime',
                        'match_reasons' => [$match['match_reason'] ?? 'Same category match'],
                        'distance_km' => null,
                    ];
                    MatchApprovalWorkflowService::submitForApproval(
                        $matchedUserId,
                        $listingId,
                        $matchData
                    );
                } catch (\Exception $e) {
                    error_log("[MatchNotificationService] Approval queue error: " . $e->getMessage());
                }
            } else {
                // Send direct notification
                self::sendMatchNotification(
                    $matchedUserId,
                    $listingId,
                    $listingTitle,
                    $listingType,
                    $creatorUserId,
                    $match
                );
            }

            // Record that we notified (or queued) this user for this listing
            self::markNotified($tenantId, $listingId, $matchedUserId, (int)($match['match_score'] ?? self::MIN_PUSH_SCORE));
            $notified++;

            if ($notified >= self::MAX_NOTIFICATIONS_PER_LISTING) {
                break;
            }
        }

        return $notified;
    }

    /**
     * Find users who have complementary listings to the newly created one.
     *
     * If the new listing is an "offer", find users who have "request" listings
     * in the same category. And vice versa.
     *
     * @param int $tenantId
     * @param int $listingId The new listing ID (excluded from results)
     * @param int $creatorUserId Creator user (excluded from results)
     * @param string $listingType 'offer' or 'request'
     * @param int|null $categoryId Category of the new listing
     * @return array Array of ['user_id', 'user_name', 'listing_title', 'match_score', 'match_reason']
     */
    private static function findMatchingUsers(
        int $tenantId,
        int $listingId,
        int $creatorUserId,
        string $listingType,
        ?int $categoryId
    ): array {
        if (empty($categoryId)) {
            return []; // Can't match without a category
        }

        // Find users with opposite-type listings in the same category
        $targetType = ($listingType === 'offer') ? 'request' : 'offer';

        $sql = "
            SELECT DISTINCT
                l.user_id,
                COALESCE(
                    CASE WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                        THEN u.organization_name
                        ELSE CONCAT(u.first_name, ' ', u.last_name)
                    END,
                    u.name
                ) as user_name,
                l.title as listing_title,
                l.id as their_listing_id,
                l.category_id
            FROM listings l
            JOIN users u ON l.user_id = u.id AND u.tenant_id = ?
            WHERE l.tenant_id = ?
              AND l.user_id != ?
              AND l.id != ?
              AND l.type = ?
              AND l.category_id = ?
              AND (l.status IS NULL OR l.status = 'active')
              AND l.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY l.created_at DESC
            LIMIT ?
        ";

        try {
            $matches = Database::query($sql, [
                $tenantId,
                $tenantId,
                $creatorUserId,
                $listingId,
                $targetType,
                $categoryId,
                self::MAX_NOTIFICATIONS_PER_LISTING * 2, // Fetch more than needed to account for filtering
            ])->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[MatchNotificationService] findMatchingUsers error: " . $e->getMessage());
            return [];
        }

        // Deduplicate by user_id (one notification per user, even if they have multiple matching listings)
        $seenUsers = [];
        $results = [];

        foreach ($matches as $match) {
            $userId = (int)$match['user_id'];
            if (isset($seenUsers[$userId])) {
                continue;
            }
            $seenUsers[$userId] = true;

            // Assign a score based on match quality
            // Same category + opposite type = strong match
            $match['match_score'] = 75; // Base score for category match
            $match['match_reason'] = "They have a matching {$targetType} in the same category";

            $results[] = $match;
        }

        return $results;
    }

    /**
     * Send a real-time match notification to a user.
     *
     * Creates an in-app notification and broadcasts via Pusher for
     * instant delivery.
     *
     * @param int $userId Recipient user ID
     * @param int $listingId The new listing ID
     * @param string $listingTitle Title of the new listing
     * @param string $listingType 'offer' or 'request'
     * @param int $creatorUserId User who created the listing
     * @param array $match Match data
     */
    private static function sendMatchNotification(
        int $userId,
        int $listingId,
        string $listingTitle,
        string $listingType,
        int $creatorUserId,
        array $match
    ): void {
        $safeTitle = htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8');
        $creatorName = htmlspecialchars($match['user_name'] ?? 'Someone', ENT_QUOTES, 'UTF-8');

        // Build a clear, actionable notification message
        if ($listingType === 'offer') {
            $message = "New match: {$creatorName} is offering \"{$safeTitle}\" — check if it matches what you need.";
        } else {
            $message = "New match: {$creatorName} is looking for \"{$safeTitle}\" — you can help!";
        }

        $link = "/listings/{$listingId}";

        // Create in-app notification (also triggers push + FCM + Pusher via Notification model)
        Notification::create($userId, $message, $link, 'listing_match');
    }

    /**
     * Check if a user was already notified about a specific listing.
     *
     * @param int $tenantId
     * @param int $listingId
     * @param int $userId
     * @return bool
     */
    private static function wasAlreadyNotified(int $tenantId, int $listingId, int $userId): bool
    {
        try {
            $result = Database::query(
                "SELECT id FROM match_notification_sent
                 WHERE tenant_id = ? AND listing_id = ? AND matched_user_id = ?",
                [$tenantId, $listingId, $userId]
            )->fetch();

            return !empty($result);
        } catch (\Exception $e) {
            // Table might not exist yet — don't block notification
            return false;
        }
    }

    /**
     * Record that a match notification was sent.
     *
     * Uses INSERT IGNORE for idempotency.
     *
     * @param int $tenantId
     * @param int $listingId
     * @param int $userId
     * @param int $matchScore
     */
    private static function markNotified(int $tenantId, int $listingId, int $userId, int $matchScore): void
    {
        try {
            Database::query(
                "INSERT IGNORE INTO match_notification_sent
                 (tenant_id, listing_id, matched_user_id, match_score, sent_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $listingId, $userId, $matchScore]
            );
        } catch (\Exception $e) {
            error_log("[MatchNotificationService] markNotified error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old match notification records (older than 30 days).
     *
     * Intentionally cross-tenant: removes old records for all tenants.
     *
     * @return int Number of records deleted
     */
    public static function cleanupOldRecords(): int
    {
        try {
            $result = Database::query(
                "DELETE FROM match_notification_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            return $result->rowCount();
        } catch (\Exception $e) {
            error_log("[MatchNotificationService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
