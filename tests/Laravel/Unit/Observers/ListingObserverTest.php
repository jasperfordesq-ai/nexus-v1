<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\Listing;
use App\Observers\ListingObserver;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ListingObserverTest extends TestCase
{
    private $searchMock;

    protected function setUp(): void
    {
        // App\Services\SearchService may already be autoloaded by app boot or an
        // earlier test in the combined run, so the alias mock MUST be created
        // before parent::setUp() and tolerate the class already existing.
        // shouldIgnoreMissing() makes boot-time/static calls no-ops; per-test
        // expectations are layered on the shared instance below.
        $this->searchMock = Mockery::mock('alias:' . SearchService::class)->shouldIgnoreMissing();
        parent::setUp();
    }

    public function test_updated_reindexes_listing(): void
    {
        $listing = new Listing();
        $listing->id = 10;

        $this->searchMock->shouldReceive('indexListing')->once()->with($listing);

        (new ListingObserver())->updated($listing);

        $this->assertTrue(true);
    }

    public function test_updated_logs_when_indexing_throws(): void
    {
        $listing = new Listing();
        $listing->id = 10;

        $this->searchMock->shouldReceive('indexListing')->andThrow(new \RuntimeException('boom'));

        Log::shouldReceive('error')
            ->once()
            ->with('ListingObserver: failed to re-index updated listing', Mockery::type('array'));

        (new ListingObserver())->updated($listing);

        $this->assertTrue(true);
    }

    public function test_deleted_removes_listing_from_index(): void
    {
        $listing = new Listing();
        $listing->id = 42;

        $this->searchMock->shouldReceive('removeListing')->once()->with(42);

        (new ListingObserver())->deleted($listing);

        $this->assertTrue(true);
    }

    public function test_deleted_logs_when_removal_throws(): void
    {
        $listing = new Listing();
        $listing->id = 42;

        $this->searchMock->shouldReceive('removeListing')->andThrow(new \RuntimeException('fail'));

        Log::shouldReceive('error')
            ->once()
            ->with('ListingObserver: failed to remove deleted listing from index', Mockery::type('array'));

        (new ListingObserver())->deleted($listing);

        $this->assertTrue(true);
    }
}
