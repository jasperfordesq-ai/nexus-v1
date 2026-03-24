<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\Listing;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Meilisearch index in sync with the listings table.
 *
 * - updated  → re-index so changed fields (title, category, etc.) are searchable immediately
 * - deleted  → remove from index so deleted listings don't appear in search results
 *
 * Creation is handled by UpdateFeedOnListingCreated listener (fired from the controller)
 * because it also needs the authenticated User and tenant context from the request.
 */
class ListingObserver
{
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
    }
}
