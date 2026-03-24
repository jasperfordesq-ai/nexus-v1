<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\ListingCreated;
use App\Models\FeedActivity;
use App\Services\SearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates a feed activity entry when a new listing is created,
 * so it appears in followers' feeds.
 */
class UpdateFeedOnListingCreated implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ListingCreated $event): void
    {
        try {
            FeedActivity::create([
                'tenant_id'   => $event->tenantId,
                'source_type' => 'listing',
                'source_id'   => $event->listing->id,
                'user_id'     => $event->user->id,
                'title'       => $event->listing->title ?? 'New Listing',
                'content'     => Str::limit($event->listing->description ?? '', 500),
                'image_url'   => $event->listing->image_url ?? null,
                'is_visible'  => true,
                'created_at'  => now(),
            ]);

            SearchService::indexListing($event->listing);
        } catch (\Throwable $e) {
            Log::error('UpdateFeedOnListingCreated listener failed', [
                'listing_id' => $event->listing->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
