<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
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
            $safeTitle = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');

            // Look up the recipient (listing author) once so we can render bell + email in their locale.
            $user = DB::table('users')
                ->where('id', $listing->user_id)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'first_name', 'name', 'preferred_language'])
                ->first();

            LocaleContext::withLocale($user, function () use ($listing, $listingId, $tenantId, $safeTitle, $user) {
                // Bell notification
                try {
                    \App\Models\Notification::createNotification(
                        (int) $listing->user_id,
                        __('emails_listings.listings.approved.notification_short', ['title' => $safeTitle]),
                        "/listings/{$listingId}",
                        'listing_approved',
                        true,
                        $tenantId
                    );
                } catch (\Throwable $e) {
                    Log::warning("AdminListingsService::approve bell notification failed for listing #{$listingId}: " . $e->getMessage());
                }

                // Email notification
                try {
                    TenantContext::setById($tenantId);
                    if ($user && !empty($user->email)) {
                        $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                        $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . "/listings/{$listingId}";
                        $html = EmailTemplateBuilder::make()
                            ->title(__('emails_misc.listing_moderation.approved_title'))
                            ->greeting($firstName)
                            ->paragraph(__('emails_misc.listing_moderation.approved_body', ['title' => $safeTitle]))
                            ->button(__('emails_misc.listing_moderation.approved_cta'), $fullUrl)
                            ->render();
                        if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.listing_moderation.approved_subject', ['title' => $safeTitle]), $html)) {
                            Log::warning("AdminListingsService::approve email failed for listing #{$listingId}");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("AdminListingsService::approve email failed for listing #{$listingId}: " . $e->getMessage());
                }
            });
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
            $safeTitle = htmlspecialchars($listing->title ?? '', ENT_QUOTES, 'UTF-8');

            // Look up recipient (listing author) once so bell + email render in their locale.
            $user = DB::table('users')
                ->where('id', $listing->user_id)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'first_name', 'name', 'preferred_language'])
                ->first();

            LocaleContext::withLocale($user, function () use ($listing, $listingId, $tenantId, $safeTitle, $reason, $user) {
                // Bell notification
                try {
                    if (!empty($reason)) {
                        $bellMsg = __('emails_listings.listings.rejected.notification', ['title' => $safeTitle, 'reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')]);
                    } else {
                        $bellMsg = __('emails_listings.listings.rejected.notification_no_reason', ['title' => $safeTitle]);
                    }
                    \App\Models\Notification::createNotification(
                        (int) $listing->user_id,
                        $bellMsg,
                        "/listings/{$listingId}",
                        'listing_rejected',
                        true,
                        $tenantId
                    );
                } catch (\Throwable $e) {
                    Log::warning("AdminListingsService::reject bell notification failed for listing #{$listingId}: " . $e->getMessage());
                }

                // Email notification
                try {
                    TenantContext::setById($tenantId);
                    if ($user && !empty($user->email)) {
                        $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                        $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . "/listings";
                        $builder   = EmailTemplateBuilder::make()
                            ->title(__('emails_misc.listing_moderation.rejected_title'))
                            ->greeting($firstName)
                            ->paragraph(__('emails_misc.listing_moderation.rejected_body', ['title' => $safeTitle]));
                        if (!empty($reason)) {
                            $builder->paragraph('<strong>' . __('emails_misc.listing_moderation.rejected_reason_label') . ':</strong> ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'));
                        }
                        $html = $builder->button(__('emails_misc.listing_moderation.rejected_cta'), $fullUrl)->render();
                        if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.listing_moderation.rejected_subject', ['title' => $safeTitle]), $html)) {
                            Log::warning("AdminListingsService::reject email failed for listing #{$listingId}");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("AdminListingsService::reject email failed for listing #{$listingId}: " . $e->getMessage());
                }
            });
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
