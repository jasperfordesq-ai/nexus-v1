<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplate;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ListingExpiryReminderService — sends reminder notifications to listing owners
 * when their listing is about to expire. Reminders are sent at 7, 3, and 1 day
 * before expiry, plus an "expired today" notification at day 0.
 *
 * Idempotent — duplicate reminders prevented by the listing_expiry_reminders_sent table.
 */
class ListingExpiryReminderService
{
    /**
     * Days before expiry at which to send reminders.
     */
    private const REMINDER_WINDOWS = [7, 3, 1];

    /**
     * Send all due listing expiry reminders for the current tenant.
     * Covers 7-day, 3-day, 1-day windows and expired-today notifications.
     *
     * @return array{sent: int, errors: int}
     */
    public function sendDueReminders(): array
    {
        $sent = 0;
        $errors = 0;

        foreach (self::REMINDER_WINDOWS as $daysBefore) {
            $result = $this->sendDueRemindersForWindow($daysBefore);
            $sent += $result['sent'];
            $errors += $result['errors'];
        }

        $expiredResult = $this->sendExpiredNotifications();
        $sent += $expiredResult['sent'];
        $errors += $expiredResult['errors'];

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Send reminders for a specific days-before-expiry window.
     *
     * @return array{sent: int, errors: int}
     */
    private function sendDueRemindersForWindow(int $daysBefore): array
    {
        $tenantId = TenantContext::getId();
        $sent = 0;
        $errors = 0;

        try {
            $listings = DB::table('listings as l')
                ->join('users as u', function ($join) use ($tenantId) {
                    $join->on('l.user_id', '=', 'u.id')
                         ->where('u.tenant_id', '=', $tenantId);
                })
                ->leftJoin('listing_expiry_reminders_sent as lers', function ($join) use ($tenantId, $daysBefore) {
                    $join->on('lers.listing_id', '=', 'l.id')
                         ->on('lers.user_id', '=', 'l.user_id')
                         ->where('lers.days_before_expiry', '=', $daysBefore)
                         ->where('lers.tenant_id', '=', $tenantId);
                })
                ->where('l.tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->whereNull('l.status')->orWhere('l.status', 'active');
                })
                ->whereNotNull('l.expires_at')
                ->where('l.expires_at', '>', now())
                ->where('l.expires_at', '<=', now()->addDays($daysBefore))
                ->whereNull('lers.id')
                ->select([
                    'l.id', 'l.title', 'l.type', 'l.user_id', 'l.expires_at',
                    'u.name', 'u.first_name', 'u.last_name', 'u.email',
                ])
                ->get();
        } catch (\Exception $e) {
            Log::error("[ListingExpiryReminderService] Query error (window={$daysBefore}): " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($listings as $listing) {
            $userId = (int) $listing->user_id;
            $listingId = (int) $listing->id;

            try {
                $this->sendReminder($userId, $listing, $daysBefore);
                $this->markReminderSent($tenantId, $listingId, $userId, $daysBefore);
                $sent++;
            } catch (\Exception $e) {
                Log::error("[ListingExpiryReminderService] Failed: listing={$listingId}, user={$userId}, window={$daysBefore}: " . $e->getMessage());
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Send "listing expired today" notifications for listings that expired in the last 24 hours.
     * Uses days_before_expiry = 0 in the tracking table.
     *
     * @return array{sent: int, errors: int}
     */
    public function sendExpiredNotifications(): array
    {
        $tenantId = TenantContext::getId();
        $sent = 0;
        $errors = 0;

        try {
            $listings = DB::table('listings as l')
                ->join('users as u', function ($join) use ($tenantId) {
                    $join->on('l.user_id', '=', 'u.id')
                         ->where('u.tenant_id', '=', $tenantId);
                })
                ->leftJoin('listing_expiry_reminders_sent as lers', function ($join) use ($tenantId) {
                    $join->on('lers.listing_id', '=', 'l.id')
                         ->on('lers.user_id', '=', 'l.user_id')
                         ->where('lers.days_before_expiry', '=', 0)
                         ->where('lers.tenant_id', '=', $tenantId);
                })
                ->where('l.tenant_id', $tenantId)
                ->whereNotNull('l.expires_at')
                ->whereBetween('l.expires_at', [now()->subDay(), now()])
                ->whereNull('lers.id')
                ->select([
                    'l.id', 'l.title', 'l.type', 'l.user_id', 'l.expires_at',
                    'u.name', 'u.first_name', 'u.last_name', 'u.email',
                ])
                ->get();
        } catch (\Exception $e) {
            Log::error("[ListingExpiryReminderService] Expired query error: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($listings as $listing) {
            $userId = (int) $listing->user_id;
            $listingId = (int) $listing->id;

            try {
                $this->sendExpiredNotification($userId, $listing);
                $this->markReminderSent($tenantId, $listingId, $userId, 0);
                $sent++;
            } catch (\Exception $e) {
                Log::error("[ListingExpiryReminderService] Expired notify failed: listing={$listingId}, user={$userId}: " . $e->getMessage());
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Send a listing expiry reminder to the listing owner.
     */
    private function sendReminder(int $userId, object $listing, int $daysBefore): void
    {
        $listingId = (int) $listing->id;
        $title = htmlspecialchars($listing->title, ENT_QUOTES, 'UTF-8');
        $expiresAt = $listing->expires_at;

        $now = new \DateTime();
        $expiryDate = new \DateTime($expiresAt);
        $diff = $now->diff($expiryDate);
        $daysLeft = $diff->days ?: 1;

        $daysText = $daysLeft === 1
            ? __('emails_listings.listings.expiry_reminder.days_one')
            : __('emails_listings.listings.expiry_reminder.days_other', ['count' => $daysLeft]);
        $expiryFormatted = date('M j, Y', strtotime($expiresAt));

        $message = __('emails_listings.listings.expiry_reminder.notification', ['title' => $title, 'days_text' => $daysText, 'expiry_date' => $expiryFormatted]);
        $link = "/listings/{$listingId}";

        Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link,
            'type' => 'listing_expiry',
            'created_at' => now(),
        ]);

        // Send email notification to listing owner
        try {
            $email = $listing->email ?? null;
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
                $frontendUrl = TenantContext::getFrontendUrl();
                $basePath = TenantContext::getSlugPrefix();
                $listingUrl = $frontendUrl . $basePath . $link;

                $ownerName = htmlspecialchars($listing->first_name ?? $listing->name ?? 'there', ENT_QUOTES, 'UTF-8');

                // Build body — for 1-day window, append urgency note
                $body = "<p>" . __('emails.common.greeting', ['name' => $ownerName]) . "</p>"
                    . "<p>" . __('emails_listings.listings.expiry_reminder.body_expires', ['title' => $title, 'days_text' => $daysText, 'expiry_date' => $expiryFormatted]) . "</p>"
                    . "<p>" . __('emails_listings.listings.expiry_reminder.body_renew') . "</p>";

                if ($daysBefore === 1) {
                    $body .= "<p><strong>" . __('emails_listings.listings.expiry_reminder.urgent_note') . "</strong></p>";
                }

                // Use warning theme for 1-day reminders, brand for 7-day and 3-day
                $theme = $daysBefore === 1 ? 'warning' : 'brand';

                $html = \App\Core\EmailTemplateBuilder::make()
                    ->theme($theme)
                    ->title(__('emails_listings.listings.expiry_reminder.heading', ['days_text' => $daysText]))
                    ->paragraph(__('emails_listings.listings.expiry_reminder.subheading'))
                    ->paragraph($body)
                    ->button(__('emails_listings.listings.expiry_reminder.cta'), $listingUrl)
                    ->render();

                $mailer = Mailer::forCurrentTenant();
                $mailer->send($email, __('emails_listings.listings.expiry_reminder.subject', ['days_text' => $daysText]), $html);
            }
        } catch (\Exception $e) {
            Log::warning("[ListingExpiryReminderService] Email send failed for user={$userId}, listing={$listingId}: " . $e->getMessage());
        }
    }

    /**
     * Send an "expired today" email notification to the listing owner.
     */
    private function sendExpiredNotification(int $userId, object $listing): void
    {
        $listingId = (int) $listing->id;
        $title = htmlspecialchars($listing->title, ENT_QUOTES, 'UTF-8');
        $link = "/listings/{$listingId}";

        Notification::create([
            'user_id' => $userId,
            'message' => __('emails_listings.listings.expired.notification', ['title' => $title]),
            'link' => $link,
            'type' => 'listing_expired',
            'created_at' => now(),
        ]);

        try {
            $email = $listing->email ?? null;
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $frontendUrl = TenantContext::getFrontendUrl();
                $basePath = TenantContext::getSlugPrefix();
                $listingUrl = $frontendUrl . $basePath . $link;

                $ownerName = htmlspecialchars($listing->first_name ?? $listing->name ?? 'there', ENT_QUOTES, 'UTF-8');

                $body = "<p>" . __('emails.common.greeting', ['name' => $ownerName]) . "</p>"
                    . "<p>" . __('emails_listings.listings.expiry_reminder.expired_body', ['title' => $title]) . "</p>";

                $html = \App\Core\EmailTemplateBuilder::make()
                    ->theme('warning')
                    ->title(__('emails_listings.listings.expiry_reminder.expired_title'))
                    ->paragraph(__('emails_listings.listings.expiry_reminder.expired_preview'))
                    ->paragraph($body)
                    ->button(__('emails_listings.listings.expiry_reminder.expired_cta'), $listingUrl)
                    ->render();

                $mailer = Mailer::forCurrentTenant();
                $mailer->send(
                    $email,
                    __('emails_listings.listings.expiry_reminder.expired_subject', ['title' => $title]),
                    $html
                );
            }
        } catch (\Exception $e) {
            Log::warning("[ListingExpiryReminderService] Expired email failed for user={$userId}, listing={$listingId}: " . $e->getMessage());
        }
    }

    /**
     * Record that a listing expiry reminder was sent (idempotent via INSERT IGNORE).
     */
    private function markReminderSent(int $tenantId, int $listingId, int $userId, int $daysBefore): void
    {
        try {
            DB::statement(
                "INSERT IGNORE INTO listing_expiry_reminders_sent
                 (tenant_id, listing_id, user_id, days_before_expiry, sent_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $listingId, $userId, $daysBefore]
            );
        } catch (\Exception $e) {
            Log::error("[ListingExpiryReminderService] markReminderSent error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old reminder tracking records (older than 30 days past expiry).
     *
     * Intentionally cross-tenant: removes expired records for all tenants.
     *
     * @return int Number of records deleted
     */
    public function cleanupOldRecords(): int
    {
        try {
            return DB::affectingStatement(
                "DELETE lers FROM listing_expiry_reminders_sent lers
                 JOIN listings l ON lers.listing_id = l.id
                 WHERE l.expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        } catch (\Exception $e) {
            Log::error("[ListingExpiryReminderService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
