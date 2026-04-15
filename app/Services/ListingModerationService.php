<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplate;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\ActivityLog;
use App\Models\Listing;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ListingModerationService — QA workflow for listings.
 *
 * When enabled per tenant, new listings enter "pending_review" status.
 * Admins can approve or reject listings through the review queue.
 */
class ListingModerationService
{
    /**
     * Check if listing moderation is enabled for the current tenant.
     */
    public function isModerationEnabled(): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $value = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'general.listing_moderation_enabled')
                ->value('setting_value');

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } catch (\Exception $e) {
            Log::warning('[ListingModeration] Failed to check moderation enabled status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Flag a listing for moderation review.
     */
    public function flag(int $tenantId, int $listingId, int $userId, string $reason): bool
    {
        $listing = Listing::where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$listing) {
            return false;
        }

        $listing->update([
            'moderation_status' => 'pending_review',
            'rejection_reason' => trim($reason),
        ]);

        return true;
    }

    /**
     * Approve a listing — sets moderation_status to 'approved' and status to 'active'.
     */
    public function approve(int $tenantId, int $listingId, int $adminId): bool
    {
        // Use explicit tenant_id parameter (callers pass it)
        $listing = Listing::where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$listing) {
            return false;
        }

        if ($listing->moderation_status !== 'pending_review') {
            return false;
        }

        $listing->update([
            'status' => 'active',
            'moderation_status' => 'approved',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // Record in feed_activity
        try {
            (new \App\Services\FeedActivityService())->recordActivity(
                $tenantId,
                $listing->user_id,
                'listing',
                $listing->id,
                [
                    'title' => $listing->title,
                    'content' => $listing->description,
                    'image_url' => $listing->image_url,
                    'metadata' => ['location' => $listing->location, 'listing_type' => $listing->type ?? 'offer'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            Log::warning("ListingModerationService::approve feed_activity failed: " . $e->getMessage());
        }

        // Notify owner
        $title = htmlspecialchars($listing->title, ENT_QUOTES, 'UTF-8');
        Notification::create([
            'user_id' => $listing->user_id,
            'message' => __('emails_listings.listings.approved.notification', ['title' => $title]),
            'link' => "/listings/{$listingId}",
            'type' => 'listing_approved',
            'created_at' => now(),
        ]);

        ActivityLog::log($adminId, 'listing_moderation_approve', "Approved listing #{$listingId}: {$listing->title}");

        return true;
    }

    /**
     * Reject a listing — sets moderation_status to 'rejected' with a reason.
     */
    public function reject(int $tenantId, int $listingId, int $adminId, string $reason = ''): bool
    {
        $listing = Listing::where('id', $listingId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$listing) {
            return false;
        }

        if ($listing->moderation_status !== 'pending_review') {
            return false;
        }

        $reason = trim($reason);
        if (empty($reason)) {
            return false;
        }

        $listing->update([
            'status' => 'rejected',
            'moderation_status' => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Notify owner (bell)
        $title = htmlspecialchars($listing->title, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        Notification::create([
            'user_id' => $listing->user_id,
            'message' => __('emails_listings.listings.rejected.notification', ['title' => $title, 'reason' => $safeReason]),
            'link' => "/listings/{$listingId}",
            'type' => 'listing_rejected',
            'created_at' => now(),
        ]);

        // Send email notification to listing owner
        try {
            $ownerEmail = DB::table('users')
                ->where('id', $listing->user_id)
                ->where('tenant_id', $tenantId)
                ->value('email');

            if (!empty($ownerEmail) && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $ownerRow = DB::table('users')
                    ->where('id', $listing->user_id)
                    ->where('tenant_id', $tenantId)
                    ->select(['first_name', 'name'])
                    ->first();

                $ownerName = htmlspecialchars($ownerRow->first_name ?? $ownerRow->name ?? 'there', ENT_QUOTES, 'UTF-8');
                $tenantName = TenantContext::get()['name'] ?? 'Project NEXUS';
                $frontendUrl = TenantContext::getFrontendUrl();
                $basePath = TenantContext::getSlugPrefix();
                $listingUrl = $frontendUrl . $basePath . "/listings/{$listingId}";

                $body = "<p>" . __('emails.common.greeting', ['name' => $ownerName]) . "</p>"
                    . "<p>" . __('emails_listings.listings.rejected.body_not_approved', ['title' => $title]) . "</p>"
                    . "<p>" . __('emails_listings.listings.rejected.body_reason', ['reason' => $safeReason]) . "</p>"
                    . "<p>" . __('emails_listings.listings.rejected.body_resubmit') . "</p>";

                $html = EmailTemplate::render(
                    __('emails_listings.listings.rejected.heading'),
                    __('emails_listings.listings.rejected.subheading'),
                    $body,
                    __('emails_listings.listings.rejected.cta'),
                    $listingUrl,
                    $tenantName
                );

                $mailer = Mailer::forCurrentTenant();
                $mailer->send($ownerEmail, __('emails_listings.listings.rejected.subject'), $html);
            }
        } catch (\Exception $e) {
            Log::warning("[ListingModerationService] reject email failed for user={$listing->user_id}, listing={$listingId}: " . $e->getMessage());
        }

        ActivityLog::log($adminId, 'listing_moderation_reject', "Rejected listing #{$listingId}: {$listing->title}. Reason: {$reason}");

        return true;
    }

    /**
     * Get pending listings for the current tenant.
     */
    public function getPending(int $tenantId): array
    {
        return Listing::where('tenant_id', $tenantId)
            ->where('moderation_status', 'pending_review')
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Reject a listing (admin shorthand without tenantId).
     */
    public function rejectListing(int $id, int $adminId, string $reason = ''): array
    {
        $tenantId = TenantContext::getId();
        $success = $this->reject($tenantId, $id, $adminId, $reason);

        return $success
            ? ['success' => true, 'error' => null]
            : ['success' => false, 'error' => 'Failed to reject listing'];
    }

    /**
     * Get the review queue with pagination.
     *
     * @return array{items: array, total: int, pages: int}
     */
    public function getReviewQueue(int $page = 1, int $limit = 20, ?string $type = null): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $limit;

        $query = DB::table('listings as l')
            ->join('users as u', 'l.user_id', '=', 'u.id')
            ->leftJoin('categories as c', 'l.category_id', '=', 'c.id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.moderation_status', 'pending_review');

        if ($type && in_array($type, ['offer', 'request'], true)) {
            $query->where('l.type', $type);
        }

        $total = $query->count();

        $items = (clone $query)
            ->select([
                'l.id', 'l.title', 'l.description', 'l.type', 'l.category_id',
                'l.image_url', 'l.location', 'l.status', 'l.moderation_status',
                'l.created_at', 'l.user_id',
                DB::raw("CASE WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL THEN u.organization_name ELSE CONCAT(u.first_name, ' ', u.last_name) END as author_name"),
                'u.avatar_url as author_avatar',
                'u.email as author_email',
                'c.name as category_name',
                'c.color as category_color',
            ])
            ->orderBy('l.created_at', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Get moderation statistics for the current tenant.
     */
    public function getStats(): array
    {
        $tenantId = TenantContext::getId();

        $result = DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('moderation_status')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN moderation_status = 'pending_review' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN moderation_status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        return [
            'total' => (int) ($result->total ?? 0),
            'pending' => (int) ($result->pending ?? 0),
            'approved' => (int) ($result->approved ?? 0),
            'rejected' => (int) ($result->rejected ?? 0),
            'moderation_enabled' => $this->isModerationEnabled(),
        ];
    }
}
