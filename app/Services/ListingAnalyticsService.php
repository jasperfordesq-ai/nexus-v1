<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * ListingAnalyticsService — Analytics for individual listings.
 *
 * Tracks and reports on view counts, contact/inquiry rate, save/favourite rate,
 * and daily/weekly/monthly breakdowns.
 */
class ListingAnalyticsService
{
    public function __construct()
    {
    }

    /**
     * Record a view for a listing.
     *
     * Deduplicates by user (if authenticated) or IP hash (if anonymous)
     * within a 1-hour window to avoid inflating counts.
     */
    public function recordView(int $listingId, ?int $userId = null, ?string $ipAddress = null): bool
    {
        $tenantId = TenantContext::getId();
        $ipHash = $ipAddress ? hash('sha256', $ipAddress . $listingId) : null;

        // Deduplicate: check if same user/IP viewed in last hour
        if ($userId) {
            $recent = DB::selectOne(
                "SELECT id FROM listing_views
                 WHERE tenant_id = ? AND listing_id = ? AND user_id = ?
                   AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 LIMIT 1",
                [$tenantId, $listingId, $userId]
            );
        } elseif ($ipHash) {
            $recent = DB::selectOne(
                "SELECT id FROM listing_views
                 WHERE tenant_id = ? AND listing_id = ? AND ip_hash = ?
                   AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 LIMIT 1",
                [$tenantId, $listingId, $ipHash]
            );
        } else {
            $recent = false;
        }

        if ($recent) {
            return false;
        }

        // Record view
        DB::insert(
            "INSERT INTO listing_views (tenant_id, listing_id, user_id, ip_hash, viewed_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$tenantId, $listingId, $userId, $ipHash]
        );

        // Increment cached counter on listing
        DB::update(
            "UPDATE listings SET view_count = view_count + 1
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        );

        return true;
    }

    /**
     * Record a contact/inquiry for a listing.
     */
    public function recordContact(int $listingId, int $userId, string $contactType = 'message'): bool
    {
        $tenantId = TenantContext::getId();

        $validTypes = ['message', 'phone', 'email', 'exchange_request'];
        if (!in_array($contactType, $validTypes, true)) {
            $contactType = 'message';
        }

        try {
            DB::insert(
                "INSERT INTO listing_contacts (tenant_id, listing_id, user_id, contact_type, contacted_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $listingId, $userId, $contactType]
            );

            DB::update(
                "UPDATE listings SET contact_count = contact_count + 1
                 WHERE id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            );

            return true;
        } catch (\Exception $e) {
            \Log::error("[ListingAnalyticsService] recordContact error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get comprehensive analytics for a listing.
     */
    public function getAnalytics(int $listingId, int $days = 30): array
    {
        $tenantId = TenantContext::getId();

        $listing = DB::selectOne(
            "SELECT id, title, view_count, contact_count, save_count, created_at, expires_at
             FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        );

        if (!$listing) {
            return ['error' => 'Listing not found'];
        }

        $listing = (array) $listing;

        $totalViews = (int) ($listing['view_count'] ?? 0);
        $totalContacts = (int) ($listing['contact_count'] ?? 0);
        $totalSaves = (int) ($listing['save_count'] ?? 0);

        // Views over time (daily breakdown)
        $viewsOverTime = array_map(fn($r) => (array) $r, DB::select(
            "SELECT DATE(viewed_at) as date, COUNT(*) as count
             FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?
               AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            [$tenantId, $listingId, $days]
        ));

        // Contacts over time
        $contactsOverTime = array_map(fn($r) => (array) $r, DB::select(
            "SELECT DATE(contacted_at) as date, COUNT(*) as count
             FROM listing_contacts
             WHERE tenant_id = ? AND listing_id = ?
               AND contacted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(contacted_at)
             ORDER BY date ASC",
            [$tenantId, $listingId, $days]
        ));

        $contactRate = $totalViews > 0 ? round(($totalContacts / $totalViews) * 100, 1) : 0;
        $saveRate = $totalViews > 0 ? round(($totalSaves / $totalViews) * 100, 1) : 0;

        // Unique viewers
        $uniqueViewers = DB::selectOne(
            "SELECT COUNT(DISTINCT COALESCE(user_id, ip_hash)) as count
             FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?",
            [$tenantId, $listingId]
        );

        // Contact types breakdown
        $contactTypes = array_map(fn($r) => (array) $r, DB::select(
            "SELECT contact_type, COUNT(*) as count
             FROM listing_contacts
             WHERE tenant_id = ? AND listing_id = ?
             GROUP BY contact_type",
            [$tenantId, $listingId]
        ));

        // Recent views (last 7 days vs previous 7 days for trend)
        $recentViews = DB::selectOne(
            "SELECT COUNT(*) as count FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?
               AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$tenantId, $listingId]
        );

        $previousViews = DB::selectOne(
            "SELECT COUNT(*) as count FROM listing_views
             WHERE tenant_id = ? AND listing_id = ?
               AND viewed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
               AND viewed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$tenantId, $listingId]
        );

        $recentCount = (int) ($recentViews->count ?? 0);
        $previousCount = (int) ($previousViews->count ?? 0);
        $viewsTrend = $previousCount > 0
            ? round((($recentCount - $previousCount) / $previousCount) * 100, 1)
            : ($recentCount > 0 ? 100 : 0);

        return [
            'listing_id' => $listingId,
            'title' => $listing['title'],
            'summary' => [
                'total_views' => $totalViews,
                'unique_viewers' => (int) ($uniqueViewers->count ?? 0),
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
     */
    public function updateSaveCount(int $listingId, bool $increment): void
    {
        $tenantId = TenantContext::getId();
        $op = $increment ? '+ 1' : '- 1';

        try {
            DB::update(
                "UPDATE listings SET save_count = GREATEST(0, CAST(save_count AS SIGNED) {$op})
                 WHERE id = ? AND tenant_id = ?",
                [$listingId, $tenantId]
            );
        } catch (\Exception $e) {
            \Log::error("[ListingAnalyticsService] updateSaveCount error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old view records (older than 90 days).
     */
    public function cleanupOldRecords(): int
    {
        try {
            return DB::delete(
                "DELETE FROM listing_views WHERE tenant_id = ? AND viewed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
                [TenantContext::getId()]
            );
        } catch (\Exception $e) {
            \Log::error("[ListingAnalyticsService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
