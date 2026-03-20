<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ListingExpiryReminderService — sends reminder notifications to listing owners
 * when their listing is about to expire. Reminders are sent at 3 days before expiry.
 *
 * Idempotent — duplicate reminders prevented by the listing_expiry_reminders_sent table.
 */
class ListingExpiryReminderService
{
    /**
     * Days before expiry to send the reminder.
     */
    private const DAYS_BEFORE_EXPIRY = 3;

    /**
     * Send all due listing expiry reminders for the current tenant.
     *
     * @return array{sent: int, errors: int}
     */
    public function sendDueReminders(): array
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
                         ->where('lers.days_before_expiry', '=', self::DAYS_BEFORE_EXPIRY)
                         ->where('lers.tenant_id', '=', $tenantId);
                })
                ->where('l.tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->whereNull('l.status')->orWhere('l.status', 'active');
                })
                ->whereNotNull('l.expires_at')
                ->where('l.expires_at', '>', now())
                ->where('l.expires_at', '<=', now()->addDays(self::DAYS_BEFORE_EXPIRY))
                ->whereNull('lers.id')
                ->select([
                    'l.id', 'l.title', 'l.type', 'l.user_id', 'l.expires_at',
                    'u.name', 'u.first_name', 'u.last_name', 'u.email',
                ])
                ->get();
        } catch (\Exception $e) {
            Log::error("[ListingExpiryReminderService] Query error: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($listings as $listing) {
            $userId = (int) $listing->user_id;
            $listingId = (int) $listing->id;

            try {
                $this->sendReminder($userId, $listing);
                $this->markReminderSent($tenantId, $listingId, $userId);
                $sent++;
            } catch (\Exception $e) {
                Log::error("[ListingExpiryReminderService] Failed: listing={$listingId}, user={$userId}: " . $e->getMessage());
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Send a listing expiry reminder to the listing owner.
     */
    private function sendReminder(int $userId, object $listing): void
    {
        $listingId = (int) $listing->id;
        $title = htmlspecialchars($listing->title, ENT_QUOTES, 'UTF-8');
        $expiresAt = $listing->expires_at;

        $now = new \DateTime();
        $expiryDate = new \DateTime($expiresAt);
        $diff = $now->diff($expiryDate);
        $daysLeft = $diff->days ?: 1;

        $daysText = $daysLeft === 1 ? '1 day' : "{$daysLeft} days";
        $expiryFormatted = date('M j, Y', strtotime($expiresAt));

        $message = "Your listing \"{$title}\" expires in {$daysText} (on {$expiryFormatted}) — renew it to keep it active.";
        $link = "/listings/{$listingId}";

        Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link,
            'type' => 'listing_expiry',
            'created_at' => now(),
        ]);
    }

    /**
     * Record that a listing expiry reminder was sent (idempotent via INSERT IGNORE).
     */
    private function markReminderSent(int $tenantId, int $listingId, int $userId): void
    {
        try {
            DB::statement(
                "INSERT IGNORE INTO listing_expiry_reminders_sent
                 (tenant_id, listing_id, user_id, days_before_expiry, sent_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $listingId, $userId, self::DAYS_BEFORE_EXPIRY]
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
