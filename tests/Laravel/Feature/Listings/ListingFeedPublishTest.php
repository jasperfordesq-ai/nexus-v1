<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Listings;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the listing → feed publishing gap: active listings
 * must reach feed_activity so they appear in the feed, both on create (observer)
 * and via the backfill command for legacy rows.
 */
class ListingFeedPublishTest extends TestCase
{
    use DatabaseTransactions;

    public function test_observer_publishes_feed_row_on_eloquent_create(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);

        $listing = Listing::create([
            'user_id' => $user->id,
            'title'   => 'New active offer',
            'type'    => 'offer',
            'status'  => 'active',
        ]);

        $this->assertDatabaseHas('feed_activity', [
            'tenant_id'   => $this->testTenantId,
            'source_type' => 'listing',
            'source_id'   => $listing->id,
            'is_visible'  => 1,
        ]);
    }

    public function test_backfill_publishes_active_listing_missing_a_feed_row(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();

        // Insert via the query builder so the observer does NOT fire — this
        // reproduces a legacy listing that was created before feed publishing
        // existed and therefore never reached the feed.
        $listingId = DB::table('listings')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'title'       => 'Legacy offer missing from the feed',
            'description' => 'Should be backfilled into the feed',
            'type'        => 'offer',
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->assertDatabaseMissing('feed_activity', [
            'tenant_id'   => $this->testTenantId,
            'source_type' => 'listing',
            'source_id'   => $listingId,
        ]);

        $this->artisan('feed:backfill-listings', ['--tenant' => (string) $this->testTenantId])
            ->assertExitCode(0);

        $this->assertDatabaseHas('feed_activity', [
            'tenant_id'   => $this->testTenantId,
            'source_type' => 'listing',
            'source_id'   => $listingId,
            'is_visible'  => 1,
        ]);
    }
}
