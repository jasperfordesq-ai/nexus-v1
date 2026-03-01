<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * ListingExpiryReminderService - Listing Expiry Reminders
 *
 * Sends reminder notifications to listing owners when their listing is about
 * to expire. Reminders are sent at 3 days before expiry.
 *
 * Listings expire based on the `expires_at` column in the `listings` table.
 * If `expires_at` is NULL, the listing never expires (no reminder sent).
 *
 * The service is idempotent — duplicate reminders are prevented by the
 * `listing_expiry_reminders_sent` tracking table.
 *
 * Usage (cron):
 *   docker exec nexus-php-app php /var/www/html/scripts/cron-listing-expiry-reminders.php
 *
 * Recommended schedule: daily at 9am
 *   0 9 * * * docker exec nexus-php-app php /var/www/html/scripts/cron-listing-expiry-reminders.php
 */
class ListingExpiryReminderService
{
    /**
     * Days before expiry to send the reminder
     */
    private const DAYS_BEFORE_EXPIRY = 3;

    /**
     * Send all due listing expiry reminders for a specific tenant.
     *
     * Queries listings expiring within DAYS_BEFORE_EXPIRY days that haven't
     * been reminded yet, and sends notifications to listing owners.
     *
     * @return array ['sent' => int, 'errors' => int]
     */
    public static function sendDueReminders(): array
    {
        $tenantId = TenantContext::getId();
        $sent = 0;
        $errors = 0;

        // Find active listings expiring within the next DAYS_BEFORE_EXPIRY days
        // that haven't had a reminder sent yet.
        $sql = "
            SELECT l.id, l.title, l.type, l.user_id, l.expires_at,
                   u.name, u.first_name, u.last_name, u.email
            FROM listings l
            JOIN users u ON l.user_id = u.id AND u.tenant_id = ?
            LEFT JOIN listing_expiry_reminders_sent lers
                ON lers.listing_id = l.id
                AND lers.user_id = l.user_id
                AND lers.days_before_expiry = ?
                AND lers.tenant_id = ?
            WHERE l.tenant_id = ?
              AND (l.status IS NULL OR l.status = 'active')
              AND l.expires_at IS NOT NULL
              AND l.expires_at > NOW()
              AND l.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
              AND lers.id IS NULL
        ";

        try {
            $listings = Database::query($sql, [
                $tenantId,
                self::DAYS_BEFORE_EXPIRY,
                $tenantId,
                $tenantId,
                self::DAYS_BEFORE_EXPIRY,
            ])->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[ListingExpiryReminderService] Query error: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($listings as $listing) {
            $userId = (int)$listing['user_id'];
            $listingId = (int)$listing['id'];

            try {
                self::sendReminder($userId, $listing);
                self::markReminderSent($tenantId, $listingId, $userId);
                $sent++;
            } catch (\Exception $e) {
                error_log("[ListingExpiryReminderService] Failed: listing={$listingId}, user={$userId}: " . $e->getMessage());
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Send a listing expiry reminder to the listing owner.
     *
     * Creates an in-app notification (which automatically triggers push via
     * Notification::create) and sends an email.
     *
     * @param int $userId
     * @param array $listing Listing data (id, title, type, expires_at, user fields)
     */
    private static function sendReminder(int $userId, array $listing): void
    {
        $listingId = (int)$listing['id'];
        $title = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
        $expiresAt = $listing['expires_at'];

        // Calculate days remaining
        $now = new \DateTime();
        $expiryDate = new \DateTime($expiresAt);
        $diff = $now->diff($expiryDate);
        $daysLeft = $diff->days;
        if ($daysLeft === 0) {
            $daysLeft = 1; // Show "1 day" if less than a full day
        }

        $daysText = $daysLeft === 1 ? '1 day' : "{$daysLeft} days";
        $expiryFormatted = date('M j, Y', strtotime($expiresAt));

        // Build notification message
        $message = "Your listing \"{$title}\" expires in {$daysText} (on {$expiryFormatted}) — renew it to keep it active.";
        $link = "/listings/{$listingId}";

        // Create in-app notification (also triggers push + FCM via Notification model)
        Notification::create($userId, $message, $link, 'listing_expiry');

        // Send email
        self::sendReminderEmail($userId, $listing, $daysText, $expiryFormatted);
    }

    /**
     * Send a listing expiry reminder email.
     *
     * @param int $userId
     * @param array $listing
     * @param string $daysText "3 days", "1 day", etc.
     * @param string $expiryFormatted Formatted expiry date
     */
    private static function sendReminderEmail(int $userId, array $listing, string $daysText, string $expiryFormatted): void
    {
        try {
            $email = $listing['email'] ?? null;
            if (empty($email)) {
                return;
            }

            $listingId = (int)$listing['id'];
            $listingTitle = htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8');
            $listingType = ucfirst($listing['type'] ?? 'listing');
            $userName = $listing['first_name'] ?? $listing['name'] ?? 'there';
            $frontendUrl = TenantContext::getFrontendUrl();
            $slugPrefix = TenantContext::getSlugPrefix();
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');

            $listingUrl = "{$frontendUrl}{$slugPrefix}/listings/{$listingId}";

            $builder = new EmailTemplateBuilder($tenantName);
            $builder->setPreviewText("Your listing \"{$listingTitle}\" expires in {$daysText}")
                ->addHero(
                    "Listing Expiring Soon",
                    "Your {$listingType} needs attention",
                    null,
                    'View Listing',
                    $listingUrl
                )
                ->addText("Hi {$userName},")
                ->addText("Your listing is expiring soon:")
                ->addCard(
                    $listingTitle,
                    "Expires on {$expiryFormatted} ({$daysText} remaining)",
                    $listing['image_url'] ?? null,
                    'Renew Listing',
                    $listingUrl
                )
                ->addText("If this listing is still relevant, visit it to renew and keep it active in the community.")
                ->addText("If you no longer need it, you can let it expire naturally — no action needed.")
                ->addButton('Renew Listing', $listingUrl, 'primary');

            $html = $builder->render();
            $subject = "Your listing \"{$listingTitle}\" expires in {$daysText}";

            $mailer = new Mailer();
            $mailer->send($email, $subject, $html);
        } catch (\Exception $e) {
            error_log("[ListingExpiryReminderService] Email error for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Record that a listing expiry reminder was sent.
     *
     * Uses INSERT IGNORE on the unique key for idempotency.
     *
     * @param int $tenantId
     * @param int $listingId
     * @param int $userId
     */
    private static function markReminderSent(int $tenantId, int $listingId, int $userId): void
    {
        try {
            Database::query(
                "INSERT IGNORE INTO listing_expiry_reminders_sent
                 (tenant_id, listing_id, user_id, days_before_expiry, sent_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $listingId, $userId, self::DAYS_BEFORE_EXPIRY]
            );
        } catch (\Exception $e) {
            error_log("[ListingExpiryReminderService] markReminderSent error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old reminder tracking records (older than 30 days past expiry).
     *
     * Intentionally cross-tenant: removes expired records for all tenants.
     *
     * @return int Number of records deleted
     */
    public static function cleanupOldRecords(): int
    {
        try {
            $result = Database::query(
                "DELETE lers FROM listing_expiry_reminders_sent lers
                 JOIN listings l ON lers.listing_id = l.id
                 WHERE l.expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            return $result->rowCount();
        } catch (\Exception $e) {
            error_log("[ListingExpiryReminderService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
