<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Listeners;

use App\Events\ListingCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

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
     *
     * TODO: Migrate logic from legacy FeedService::createActivity().
     *       The legacy code lives at:
     *       - src/Services/FeedService.php (createActivity method)
     *       - src/Services/SearchService.php (index the new listing)
     */
    public function handle(ListingCreated $event): void
    {
        // TODO: Create feed activity via FeedService::createActivity()
        // TODO: Index listing in search via SearchService::indexListing()
        // TODO: Notify followers via NotificationService
    }
}
