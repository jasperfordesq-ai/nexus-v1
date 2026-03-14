<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;
use Nexus\Models\ActivityLog;

/**
 * ListingModerationService - QA workflow for listings
 *
 * Configurable per tenant:
 * - When enabled, new listings enter "pending_review" status
 * - Admin review queue: list pending listings
 * - Approve: sets moderation_status = 'approved', listing becomes active
 * - Reject: sets moderation_status = 'rejected' with reason
 * - Auto-publish: when moderation is disabled, listings go straight to active
 *
 * Tenant setting: `listing_moderation_enabled` (boolean)
 */
class ListingModerationService
{
    /**
     * Check if listing moderation is enabled for the current tenant.
     *
     * @return bool
     */
    public static function isModerationEnabled(): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $setting = Database::query(
                "SELECT setting_value FROM tenant_settings
                 WHERE tenant_id = ? AND setting_key = 'general.listing_moderation_enabled'",
                [$tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $setting && $setting['setting_value'] === '1';
        } catch (\Exception $e) {
            // Table may not exist; default to disabled
            return false;
        }
    }

    /**
     * Determine the initial moderation status for a new listing.
     *
     * @return string|null 'pending_review' if moderation enabled, null otherwise
     */
    public static function getInitialModerationStatus(): ?string
    {
        return self::isModerationEnabled() ? 'pending_review' : null;
    }

    /**
     * Get the review queue: pending listings for the current tenant.
     *
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string|null $type Filter by listing type (offer/request)
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getReviewQueue(int $page = 1, int $perPage = 20, ?string $type = null): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $conditions = "l.tenant_id = ? AND l.moderation_status = 'pending_review'";
        $params = [$tenantId];

        if ($type && in_array($type, ['offer', 'request'], true)) {
            $conditions .= " AND l.type = ?";
            $params[] = $type;
        }

        // Total count
        $countResult = Database::query(
            "SELECT COUNT(*) as total FROM listings l WHERE {$conditions}",
            $params
        )->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($countResult['total'] ?? 0);

        // Paginated results
        $paramsWithPagination = array_merge($params, [$perPage, $offset]);
        $items = Database::query(
            "SELECT l.id, l.title, l.description, l.type, l.category_id, l.image_url,
                    l.location, l.status, l.moderation_status, l.created_at, l.user_id,
                    CASE
                        WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL
                        THEN u.organization_name
                        ELSE CONCAT(u.first_name, ' ', u.last_name)
                    END as author_name,
                    u.avatar_url as author_avatar, u.email as author_email,
                    c.name as category_name, c.color as category_color
             FROM listings l
             JOIN users u ON l.user_id = u.id
             LEFT JOIN categories c ON l.category_id = c.id
             WHERE {$conditions}
             ORDER BY l.created_at ASC
             LIMIT ? OFFSET ?",
            $paramsWithPagination
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Approve a listing.
     *
     * Sets moderation_status to 'approved' and status to 'active'.
     * Notifies the listing owner.
     *
     * @param int $listingId Listing ID
     * @param int $adminId Admin user ID performing the approval
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function approve(int $listingId, int $adminId): array
    {
        $tenantId = TenantContext::getId();

        $listing = Database::query(
            "SELECT id, title, user_id, moderation_status
             FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        if ($listing['moderation_status'] !== 'pending_review') {
            return ['success' => false, 'error' => 'Listing is not pending review'];
        }

        Database::query(
            "UPDATE listings
             SET status = 'active',
                 moderation_status = 'approved',
                 reviewed_by = ?,
                 reviewed_at = NOW(),
                 rejection_reason = NULL,
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$adminId, $listingId, $tenantId]
        );

        // Record in feed_activity now that listing is active
        try {
            $full = Database::query(
                "SELECT description, image_url, location, type FROM listings WHERE id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);
            if ($full) {
                FeedActivityService::recordActivity($tenantId, (int)$listing['user_id'], 'listing', $listingId, [
                    'title' => $listing['title'],
                    'content' => $full['description'],
                    'image_url' => $full['image_url'],
                    'metadata' => ['location' => $full['location'], 'listing_type' => $full['type'] ?? 'offer'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Exception $e) {
            error_log("ListingModerationService::approve feed_activity failed: " . $e->getMessage());
        }

        // Notify owner
        $title = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
        Notification::create(
            (int)$listing['user_id'],
            "Your listing \"{$title}\" has been approved and is now active.",
            "/listings/{$listingId}",
            'listing_approved'
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'listing_moderation_approve',
            "Approved listing #{$listingId}: {$listing['title']}"
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Reject a listing.
     *
     * Sets moderation_status to 'rejected' with a reason.
     * Notifies the listing owner.
     *
     * @param int $listingId Listing ID
     * @param int $adminId Admin user ID
     * @param string $reason Rejection reason
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function reject(int $listingId, int $adminId, string $reason): array
    {
        $tenantId = TenantContext::getId();

        $listing = Database::query(
            "SELECT id, title, user_id, moderation_status
             FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        if ($listing['moderation_status'] !== 'pending_review') {
            return ['success' => false, 'error' => 'Listing is not pending review'];
        }

        $reason = trim($reason);
        if (empty($reason)) {
            return ['success' => false, 'error' => 'Rejection reason is required'];
        }

        Database::query(
            "UPDATE listings
             SET status = 'rejected',
                 moderation_status = 'rejected',
                 reviewed_by = ?,
                 reviewed_at = NOW(),
                 rejection_reason = ?,
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$adminId, $reason, $listingId, $tenantId]
        );

        // Notify owner
        $title = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        Notification::create(
            (int)$listing['user_id'],
            "Your listing \"{$title}\" was not approved. Reason: {$safeReason}",
            "/listings/{$listingId}",
            'listing_rejected'
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'listing_moderation_reject',
            "Rejected listing #{$listingId}: {$listing['title']}. Reason: {$reason}"
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Get moderation statistics for the current tenant.
     *
     * @return array Stats
     */
    public static function getStats(): array
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN moderation_status = 'pending_review' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN moderation_status = 'rejected' THEN 1 ELSE 0 END) as rejected
             FROM listings
             WHERE tenant_id = ? AND moderation_status IS NOT NULL",
            [$tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'total' => (int)($result['total'] ?? 0),
            'pending' => (int)($result['pending'] ?? 0),
            'approved' => (int)($result['approved'] ?? 0),
            'rejected' => (int)($result['rejected'] ?? 0),
            'moderation_enabled' => self::isModerationEnabled(),
        ];
    }
}
