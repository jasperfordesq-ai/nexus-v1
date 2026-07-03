<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\Listing;
use App\Observers\Concerns\IndexesEmbeddings;
use App\Services\FeedActivityService;
use App\Services\SearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps the Meilisearch index AND the feed_activity row in sync with the
 * listings table.
 *
 * - created  → publish to the feed if visible (belt-and-braces with the
 *              UpdateFeedOnListingCreated listener; both are idempotent)
 * - updated  → re-index + re-sync feed visibility (e.g. moderation approve,
 *              suspend, reactivate) so a listing appears/disappears correctly
 * - deleted  → remove from index + feed
 *
 * The feed sync closes the gap where listings created outside the controller
 * happy-path (imports, admin tools, status changes) never reached the feed.
 */
class ListingObserver
{
    use IndexesEmbeddings;

    public function created(Listing $listing): void
    {
        $this->syncFeed($listing);
        $this->reindexEmbedding($listing, 'listing');
    }

    public function updated(Listing $listing): void
    {
        try {
            SearchService::indexListing($listing);
        } catch (\Throwable $e) {
            Log::error('ListingObserver: failed to re-index updated listing', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
        $this->syncFeed($listing);
        $this->reindexEmbedding($listing, 'listing');
    }

    public function deleted(Listing $listing): void
    {
        try {
            SearchService::removeListing($listing->id);
        } catch (\Throwable $e) {
            Log::error('ListingObserver: failed to remove deleted listing from index', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }

        try {
            DB::table('feed_activity')
                ->where('tenant_id', $listing->tenant_id)
                ->where('source_type', 'listing')
                ->where('source_id', $listing->id)
                ->delete();
        } catch (\Throwable $e) {
            Log::error('ListingObserver: failed to remove listing feed activity', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $this->deleteEmbedding($listing, 'listing');
    }

    /**
     * Publish/hide the listing's feed_activity row based on its current
     * visibility. A visible listing is `status = active` and not held by
     * moderation (moderation_status null or 'approved').
     */
    private function syncFeed(Listing $listing): void
    {
        try {
            $moderationStatus = $listing->moderation_status ?? 'approved';
            $visible = ($listing->status ?? 'active') === 'active' && $moderationStatus === 'approved';

            if ($visible) {
                app(FeedActivityService::class)->recordActivity(
                    (int) $listing->tenant_id,
                    (int) $listing->user_id,
                    'listing',
                    (int) $listing->id,
                    [
                        'title'     => $listing->title,
                        'content'   => Str::limit($listing->description ?? '', 500),
                        'image_url' => $listing->image_url ?? null,
                    ]
                );
            }

            // recordActivity's upsert never touches is_visible, so set it
            // explicitly here — this is what re-shows a reactivated listing and
            // hides a suspended/rejected one without deleting engagement.
            DB::table('feed_activity')
                ->where('tenant_id', $listing->tenant_id)
                ->where('source_type', 'listing')
                ->where('source_id', $listing->id)
                ->update(['is_visible' => $visible ? 1 : 0]);
        } catch (\Throwable $e) {
            Log::error('ListingObserver: failed to sync listing feed activity', [
                'listing_id' => $listing->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
