<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Listeners;

use App\Core\TenantContext;
use App\Events\ListingCreated;
use App\Listeners\UpdateFeedOnListingCreated;
use App\Models\FeedActivity;
use App\Models\Listing;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * Regression test for the UpdateFeedOnListingCreated listener idempotency bug
 * (prod ERROR 2026-06-18: "SQLSTATE[23000] ... 1062 Duplicate entry
 * '2-listing-491' for key 'uq_tenant_source'").
 *
 * The listener is ShouldQueue, so a ListingCreated event can be re-delivered
 * (queue retry / re-dispatch). The old code used a raw FeedActivity::create()
 * which threw 1062 on the second delivery — and because that throw happened
 * BEFORE SearchService::indexListing(), a re-fired listing also silently missed
 * search re-indexing while logging a listener-failure error.
 *
 * Firing the event twice must now: (a) leave exactly ONE feed_activity row, and
 * (b) NOT log a listener failure (proving the duplicate path no longer throws).
 */
class UpdateFeedOnListingCreatedTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Force search indexing into a deterministic no-op: Meilisearch may or
        // may not be reachable from the test process, and indexListing() runs
        // inside the same try/catch — letting it throw here would log the same
        // "listener failed" error we are asserting against.
        $prop = new \ReflectionProperty(SearchService::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    protected function tearDown(): void
    {
        // Reset the cached availability so we don't leak state into other tests.
        $prop = new \ReflectionProperty(SearchService::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    public function test_refired_listing_created_event_is_idempotent_and_logs_no_failure(): void
    {
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->create([
            'tenant_id'         => $this->testTenantId,
            'user_id'           => $user->id,
            'status'            => 'active',
            'moderation_status' => 'approved',
        ]);

        Log::spy();

        $listener = new UpdateFeedOnListingCreated();
        $event = new ListingCreated($listing, $user, $this->testTenantId);

        // Fire twice — simulates the queue retry / re-dispatch that produced the
        // production 1062 duplicate-key error.
        $listener->handle($event);
        $listener->handle($event);

        $count = FeedActivity::withoutGlobalScopes()
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'listing')
            ->where('source_id', $listing->id)
            ->count();

        $this->assertSame(
            1,
            $count,
            'Exactly one feed_activity row must exist after the event fires twice'
        );

        // The old raw insert logged this exact message on the duplicate; the
        // idempotent firstOrCreate must not.
        Log::shouldNotHaveReceived('error');
    }
}
