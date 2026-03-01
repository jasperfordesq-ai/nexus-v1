<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ListingAnalyticsService - Analytics for individual listings
 *
 * Tracks and reports on:
 * - View counts (total + over time)
 * - Contact/inquiry rate
 * - Save/favourite rate
 * - Daily/weekly/monthly breakdowns
 */
class ListingAnalyticsService
{
    /**
     * Record a view for a listing.
     *
     * Deduplicates by user (if authenticated) or IP hash (if anonymous)
     * within a 1-hour window to avoid inflating counts.
     *
     * @param int $listingId Listing ID
     * @param int|null $userId Viewer user ID (null for anonymous)
     * @param string|null $ipAddress Viewer IP address (for anonymous dedup)
     * @return bool Whether a new view was recorded
     */
    public static function recordView(int $listingId, ?int $userId = null, ?string $ipAddress = null): bool
    {
        $tenantId = TenantContext::getId();
        $ipHash = $ipAddress ? hash('sha256', $ipAddress . $listingId) : null;

        // Deduplicate: check if same user/IP viewed in last hour
        if ($userId) {
            $recent = Database::query(
                "SELECT id FROM listing_views
                 WHERE tenant_id = ? AND listing_id = ? AND user_id = ?
                   AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 LIMIT 1",
                [$tenantId, $listingId, $userId]
            )->fetch(\PDO::FETCH_ASSOC);
        } elseif ($ipHash) {
            $recent = Database::query(
                "SELECT id FROM listing_views
                 WHERE tenant_id = ? AND listing_id = ? AND ip_hash = ?
                   AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 LIMIT 1",
                [$tenantId, $listingId, $ipHash]
            )->fetch(\PDO::FETCH_ASSOC);
        } else {
            $recent = false;
        }

        if ($recent) {
            return false; // Already viewed recently
        }

        // Record view
        Database::query(
            "INSERT INTO listing_views (tenant_id, listing_id, user_id, ip_hash, viewed_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$tenantId, $listingId, $userId, $ipHash]
        );

        // Increment cached counter on listing
        Database::query(
            "UPDATE listings SET view_count = view_count + 1
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        );

        return true;
    }

    /**
     * Record a contact/inquiry for a listing.
     *
     * @param int $listingId Listing ID
     * @param int $userId User who contacted
     * @param string $contactType Type of contact (message, phone, email, exchange_request)
     * @return bool Success
     */
    public static function recordContact(int $listingId, int $userId, string $contactType = 'message'): bool
    {
        $tenantId = TenantContext::getId();

        $validTypes = ['message', 'phone', 'email', 'exchange_request'];
        if (!in_array($contactType, $validTypes, true)) {
            $contactType = 'message';
        }

        try {
            Database::query(
                "INSERT INTO listing_contacts (tenant_id, listing_id, user_id, contact_type, contacted_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $listingId, $userId, $contactType]
            );

            // Increment cached counter
            Database::query(
                "UPDATE listings SET contact_count = contact_count + 1
                 WHERE id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            error_log("[ListingAnalyticsService] recordContact error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get comprehensive analytics for a listing.
     *
     * @param int $listingId Listing ID
     * @param int $days Number of days to look back (default 30)
     * @return array Analytics data
     */
    public static function getAnalytics(int $listingId, int $days = 30): array
    {
        $tenantId = TenantContext::getId();

        // Verify listing belongs to tenant
        $listing = Database::query(
            "SELECT id, title, view_count, contact_count, save_count, created_at, expires_at
             FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return ['error' => 'Listing not found'];
        }

        // Total views
        $totalViews = (int)($listing['view_count'] ?? 0);

        // Total contacts
        $totalContacts = (int)($listing['contact_count'] ?? 0);

        // Total saves
        $totalSaves = (int)($listing['save_count'] ?? 0);

        // Views over time (daily breakdown)
        $viewsOverTime = Database::query(
            "SELECT DATE(viewed_at) as date, COUNT(*) as count
             FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?
               AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            [$tenantId, $listingId, $days]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Contacts over time (daily breakdown)
        $contactsOverTime = Database::query(
            "SELECT DATE(contacted_at) as date, COUNT(*) as count
             FROM listing_contacts
             WHERE tenant_id = ? AND listing_id = ?
               AND contacted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(contacted_at)
             ORDER BY date ASC",
            [$tenantId, $listingId, $days]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Contact rate (contacts / views)
        $contactRate = $totalViews > 0 ? round(($totalContacts / $totalViews) * 100, 1) : 0;

        // Save rate (saves / views)
        $saveRate = $totalViews > 0 ? round(($totalSaves / $totalViews) * 100, 1) : 0;

        // Unique viewers
        $uniqueViewers = Database::query(
            "SELECT COUNT(DISTINCT COALESCE(user_id, ip_hash)) as count
             FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?",
            [$tenantId, $listingId]
        )->fetch(\PDO::FETCH_ASSOC);

        // Contact types breakdown
        $contactTypes = Database::query(
            "SELECT contact_type, COUNT(*) as count
             FROM listing_contacts
             WHERE tenant_id = ? AND listing_id = ?
             GROUP BY contact_type",
            [$tenantId, $listingId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Recent views (last 7 days vs previous 7 days for trend)
        $recentViews = Database::query(
            "SELECT COUNT(*) as count FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?
               AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$tenantId, $listingId]
        )->fetch(\PDO::FETCH_ASSOC);

        $previousViews = Database::query(
            "SELECT COUNT(*) as count FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?
               AND viewed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
               AND viewed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$tenantId, $listingId]
        )->fetch(\PDO::FETCH_ASSOC);

        $recentCount = (int)($recentViews['count'] ?? 0);
        $previousCount = (int)($previousViews['count'] ?? 0);
        $viewsTrend = $previousCount > 0
            ? round((($recentCount - $previousCount) / $previousCount) * 100, 1)
            : ($recentCount > 0 ? 100 : 0);

        return [
            'listing_id' => $listingId,
            'title' => $listing['title'],
            'summary' => [
                'total_views' => $totalViews,
                'unique_viewers' => (int)($uniqueViewers['count'] ?? 0),
                'total_contacts' => $totalContacts,
                'total_saves' => $totalSaves,
                'contact_rate' => $contactRate,
                'save_rate' => $saveRate,
                'views_trend_percent' => $viewsTrend,
            ],
            'views_over_time' => $viewsOverTime,
            'contacts_over_time' => $contactsOverTime,
            'contact_types' => $contactTypes,
            'period_days' => $days,
            'created_at' => $listing['created_at'],
            'expires_at' => $listing['expires_at'],
        ];
    }

    /**
     * Update save_count cache when a listing is saved/unsaved.
     *
     * @param int $listingId Listing ID
     * @param bool $increment True to increment, false to decrement
     */
    public static function updateSaveCount(int $listingId, bool $increment): void
    {
        $tenantId = TenantContext::getId();
        $op = $increment ? '+ 1' : '- 1';

        try {
            Database::query(
                "UPDATE listings SET save_count = GREATEST(0, CAST(save_count AS SIGNED) {$op})
                 WHERE id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            );
        } catch (\Exception $e) {
            error_log("[ListingAnalyticsService] updateSaveCount error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old view records (older than 90 days).
     * Keeps the cached counts on the listings table.
     *
     * @return int Number of records deleted
     */
    public static function cleanupOldRecords(): int
    {
        try {
            $result = Database::query(
                "DELETE FROM listing_views WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            return $result->rowCount();
        } catch (\Exception $e) {
            error_log("[ListingAnalyticsService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
