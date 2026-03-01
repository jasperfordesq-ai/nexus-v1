<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ListingFeaturedService - Featured/Boosted listing management
 *
 * Manages the is_featured flag and featured_until datetime:
 * - Admin can feature/unfeature listings
 * - Featured listings sort to top in search results
 * - Featured listings get a visual badge
 * - Auto-unfeature when featured_until passes
 */
class ListingFeaturedService
{
    /**
     * Default feature duration in days
     */
    private const DEFAULT_FEATURE_DAYS = 7;

    /**
     * Feature a listing.
     *
     * @param int $listingId Listing ID
     * @param int|null $days Number of days to feature (null = indefinite)
     * @return array ['success' => bool, 'error' => string|null, 'featured_until' => string|null]
     */
    public static function featureListing(int $listingId, ?int $days = null): array
    {
        $tenantId = TenantContext::getId();

        // Verify listing exists
        $listing = Database::query(
            "SELECT id, status FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found', 'featured_until' => null];
        }

        // Calculate featured_until
        $featuredUntil = null;
        if ($days !== null) {
            $featureDays = max(1, min(365, $days));
            $result = Database::query(
                "SELECT DATE_ADD(NOW(), INTERVAL ? DAY) as date",
                [$featureDays]
            )->fetch(\PDO::FETCH_ASSOC);
            $featuredUntil = $result['date'];
        }

        Database::query(
            "UPDATE listings
             SET is_featured = 1, featured_until = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$featuredUntil, $listingId, $tenantId]
        );

        return [
            'success' => true,
            'error' => null,
            'featured_until' => $featuredUntil,
        ];
    }

    /**
     * Unfeature a listing.
     *
     * @param int $listingId Listing ID
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function unfeatureListing(int $listingId): array
    {
        $tenantId = TenantContext::getId();

        $listing = Database::query(
            "SELECT id FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found'];
        }

        Database::query(
            "UPDATE listings
             SET is_featured = 0, featured_until = NULL, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Process expired featured listings (auto-unfeature).
     *
     * @return int Number of listings un-featured
     */
    public static function processExpiredFeatured(): int
    {
        $tenantId = TenantContext::getId();

        try {
            $result = Database::query(
                "UPDATE listings
                 SET is_featured = 0, featured_until = NULL, updated_at = NOW()
                 WHERE tenant_id = ?
                   AND is_featured = 1
                   AND featured_until IS NOT NULL
                   AND featured_until <= NOW()",
                [$tenantId]
            );
            return $result->rowCount();
        } catch (\Exception $e) {
            error_log("[ListingFeaturedService] processExpiredFeatured error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get featured listings for the tenant.
     *
     * @param int $limit Max listings
     * @return array Featured listings
     */
    public static function getFeaturedListings(int $limit = 10): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT l.id, l.title, l.description, l.type, l.category_id, l.image_url,
                    l.location, l.status, l.is_featured, l.featured_until,
                    l.created_at, l.user_id, l.view_count,
                    CASE
                        WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL
                        THEN u.organization_name
                        ELSE CONCAT(u.first_name, ' ', u.last_name)
                    END as author_name,
                    u.avatar_url as author_avatar,
                    c.name as category_name, c.color as category_color
             FROM listings l
             JOIN users u ON l.user_id = u.id
             LEFT JOIN categories c ON l.category_id = c.id
             WHERE l.tenant_id = ?
               AND l.status = 'active'
               AND l.is_featured = 1
               AND (l.featured_until IS NULL OR l.featured_until > NOW())
             ORDER BY l.created_at DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a listing is currently featured.
     *
     * @param int $listingId Listing ID
     * @return bool
     */
    public static function isFeatured(int $listingId): bool
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT is_featured, featured_until FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !$row['is_featured']) {
            return false;
        }

        // Check if feature period has expired
        if ($row['featured_until'] !== null) {
            return strtotime($row['featured_until']) > time();
        }

        return true;
    }
}
