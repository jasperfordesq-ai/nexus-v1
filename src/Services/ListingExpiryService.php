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
 * ListingExpiryService - Auto-expiry and renewal workflow
 *
 * Handles:
 * - Processing expired listings (marking them as 'expired')
 * - One-click renewal (extends expiry by 30 days)
 * - Renewal tracking (renewed_at, renewal_count)
 *
 * Works alongside ListingExpiryReminderService which handles
 * sending reminders before expiry.
 *
 * Usage (cron):
 *   docker exec nexus-php-app php /var/www/html/scripts/cron-listing-expiry.php
 *
 * Recommended schedule: every hour
 *   0 * * * * docker exec nexus-php-app php /var/www/html/scripts/cron-listing-expiry.php
 */
class ListingExpiryService
{
    /**
     * Default renewal period in days
     */
    private const RENEWAL_DAYS = 30;

    /**
     * Maximum number of renewals allowed per listing
     */
    private const MAX_RENEWALS = 12;

    /**
     * Process all expired listings for the current tenant.
     *
     * Finds active listings where expires_at has passed and marks them as 'expired'.
     * Sends a notification to the listing owner.
     *
     * @return array ['expired' => int, 'errors' => int]
     */
    public static function processExpiredListings(): array
    {
        $tenantId = TenantContext::getId();
        $expired = 0;
        $errors = 0;

        try {
            $listings = Database::query(
                "SELECT l.id, l.title, l.type, l.user_id, l.expires_at
                 FROM listings l
                 WHERE l.tenant_id = ?
                   AND (l.status IS NULL OR l.status = 'active')
                   AND l.expires_at IS NOT NULL
                   AND l.expires_at <= NOW()",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[ListingExpiryService] Query error: " . $e->getMessage());
            return ['expired' => 0, 'errors' => 1];
        }

        foreach ($listings as $listing) {
            try {
                // Mark as expired
                Database::query(
                    "UPDATE listings SET status = 'expired', updated_at = NOW()
                     WHERE id = ? AND tenant_id = ?",
                    [(int)$listing['id'], $tenantId]
                );

                // Notify the owner
                $title = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
                $message = "Your listing \"{$title}\" has expired. You can renew it to make it active again.";
                $link = "/listings/{$listing['id']}";
                Notification::create((int)$listing['user_id'], $message, $link, 'listing_expired');

                $expired++;
            } catch (\Exception $e) {
                error_log("[ListingExpiryService] Failed to expire listing {$listing['id']}: " . $e->getMessage());
                $errors++;
            }
        }

        return ['expired' => $expired, 'errors' => $errors];
    }

    /**
     * Process expired listings across ALL tenants (for cron job).
     *
     * Iterates all active tenants and processes expired listings for each.
     *
     * @return array ['total_expired' => int, 'total_errors' => int, 'tenants_processed' => int]
     */
    public static function processAllTenants(): array
    {
        $totalExpired = 0;
        $totalErrors = 0;
        $tenantsProcessed = 0;

        try {
            $tenants = Database::query(
                "SELECT id FROM tenants WHERE status = 'active'"
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[ListingExpiryService] Failed to fetch tenants: " . $e->getMessage());
            return ['total_expired' => 0, 'total_errors' => 1, 'tenants_processed' => 0];
        }

        foreach ($tenants as $tenant) {
            try {
                TenantContext::setId((int)$tenant['id']);
                $result = self::processExpiredListings();
                $totalExpired += $result['expired'];
                $totalErrors += $result['errors'];
                $tenantsProcessed++;
            } catch (\Exception $e) {
                error_log("[ListingExpiryService] Error processing tenant {$tenant['id']}: " . $e->getMessage());
                $totalErrors++;
            }
        }

        return [
            'total_expired' => $totalExpired,
            'total_errors' => $totalErrors,
            'tenants_processed' => $tenantsProcessed,
        ];
    }

    /**
     * Renew a listing (extend expiry by RENEWAL_DAYS).
     *
     * @param int $listingId Listing ID
     * @param int $userId User requesting renewal (must be owner or admin)
     * @return array ['success' => bool, 'error' => string|null, 'new_expires_at' => string|null]
     */
    public static function renewListing(int $listingId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Fetch listing
        $listing = Database::query(
            "SELECT id, user_id, status, expires_at, renewal_count
             FROM listings
             WHERE id = ? AND tenant_id = ?",
            [$listingId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$listing) {
            return ['success' => false, 'error' => 'Listing not found', 'new_expires_at' => null];
        }

        // Authorization: owner or admin
        if ((int)$listing['user_id'] !== $userId) {
            $user = Database::query(
                "SELECT role, is_super_admin FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$user || ($user['role'] !== 'admin' && !$user['is_super_admin'])) {
                return ['success' => false, 'error' => 'You do not have permission to renew this listing', 'new_expires_at' => null];
            }
        }

        // Check renewal limit
        $currentCount = (int)($listing['renewal_count'] ?? 0);
        if ($currentCount >= self::MAX_RENEWALS) {
            return ['success' => false, 'error' => 'Maximum renewal limit reached (' . self::MAX_RENEWALS . ' renewals)', 'new_expires_at' => null];
        }

        // Calculate new expiry date
        // If listing is expired or has no expiry, start from NOW
        // If still active, extend from current expiry
        $baseDate = 'NOW()';
        if ($listing['status'] === 'active' && !empty($listing['expires_at'])) {
            $expiresAt = new \DateTime($listing['expires_at']);
            $now = new \DateTime();
            if ($expiresAt > $now) {
                $baseDate = "'" . $expiresAt->format('Y-m-d H:i:s') . "'";
            }
        }

        $newExpiresAt = Database::query(
            "SELECT DATE_ADD({$baseDate}, INTERVAL ? DAY) as new_date",
            [self::RENEWAL_DAYS]
        )->fetch(\PDO::FETCH_ASSOC)['new_date'];

        // Update listing
        Database::query(
            "UPDATE listings
             SET status = 'active',
                 expires_at = ?,
                 renewed_at = NOW(),
                 renewal_count = renewal_count + 1,
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$newExpiresAt, $listingId, $tenantId]
        );

        // Log activity
        try {
            \Nexus\Models\ActivityLog::log(
                $userId,
                'listing_renewed',
                "Renewed listing #{$listingId}: " . ($listing['title'] ?? 'Unknown')
            );
        } catch (\Exception $e) {
            // Non-critical
        }

        return [
            'success' => true,
            'error' => null,
            'new_expires_at' => $newExpiresAt,
        ];
    }

    /**
     * Set expiry date for a listing.
     *
     * @param int $listingId Listing ID
     * @param string|null $expiresAt ISO datetime string, or null to remove expiry
     * @return bool Success
     */
    public static function setExpiry(int $listingId, ?string $expiresAt): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE listings SET expires_at = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$expiresAt, $listingId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("[ListingExpiryService] setExpiry error: " . $e->getMessage());
            return false;
        }
    }
}
