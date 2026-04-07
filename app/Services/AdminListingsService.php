<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminListingsService — Laravel DI-based service for admin listing management.
 *
 * Handles listing approval, rejection, and moderation statistics.
 */
class AdminListingsService
{
    /**
     * Get pending listings for a tenant.
     */
    public function getPending(int $tenantId, int $limit = 20, int $offset = 0): array
    {
        $query = DB::table('listings as l')
            ->leftJoin('users as u', 'l.user_id', '=', 'u.id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', 'pending')
            ->select('l.*', 'u.name as author_name');

        $total = $query->count();
        $items = $query->orderByDesc('l.created_at')
            ->offset($offset)
            ->limit(min($limit, 100))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Approve a pending listing.
     */
    public function approve(int $listingId, int $tenantId, int $adminId): bool
    {
        // Fetch listing data before update for notification
        $listing = DB::table('listings')
            ->where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->first();

        if (!$listing) {
            return false;
        }

        $affected = DB::table('listings')
            ->where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->update([
                'status'      => 'active',
                'approved_by' => $adminId,
                'approved_at' => now(),
                'updated_at'  => now(),
            ]);

        if ($affected > 0) {
            // Notify listing owner
            try {
                $title = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');
                \App\Models\Notification::createNotification(
                    (int) $listing->user_id,
                    __('emails_listings.listings.approved.notification_short', ['title' => $title]),
                    "/listings/{$listingId}",
                    'listing_approved',
                    true,
                    $tenantId
                );
            } catch (\Throwable $e) {
                Log::warning("AdminListingsService::approve notification failed for listing #{$listingId}: " . $e->getMessage());
            }
        }

        return $affected > 0;
    }

    /**
     * Reject a pending listing.
     */
    public function reject(int $listingId, int $tenantId, int $adminId, ?string $reason = null): bool
    {
        // Fetch listing data before update for notification
        $listing = DB::table('listings')
            ->where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->first();

        if (!$listing) {
            return false;
        }

        $affected = DB::table('listings')
            ->where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->update([
                'status'          => 'rejected',
                'rejection_reason' => $reason,
                'approved_by'     => $adminId,
                'updated_at'      => now(),
            ]);

        if ($affected > 0) {
            // Notify listing owner
            try {
                $title = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');
                if (!empty($reason)) {
                    $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                    $message = __('emails_listings.listings.rejected.notification', ['title' => $title, 'reason' => $safeReason]);
                } else {
                    $message = __('emails_listings.listings.rejected.notification_no_reason', ['title' => $title]);
                }
                \App\Models\Notification::createNotification(
                    (int) $listing->user_id,
                    $message,
                    "/listings/{$listingId}",
                    'listing_rejected',
                    true,
                    $tenantId
                );
            } catch (\Throwable $e) {
                Log::warning("AdminListingsService::reject notification failed for listing #{$listingId}: " . $e->getMessage());
            }
        }

        return $affected > 0;
    }

    /**
     * Get listing statistics for admin dashboard.
     */
    public function getStats(int $tenantId): array
    {
        $rows = DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return [
            'active'   => (int) ($rows['active'] ?? 0),
            'pending'  => (int) ($rows['pending'] ?? 0),
            'rejected' => (int) ($rows['rejected'] ?? 0),
            'expired'  => (int) ($rows['expired'] ?? 0),
            'total'    => array_sum(array_map('intval', $rows)),
        ];
    }
}
