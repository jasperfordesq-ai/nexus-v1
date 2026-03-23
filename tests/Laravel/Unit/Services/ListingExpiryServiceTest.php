<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Listing;
use App\Models\Notification;
use App\Services\ListingExpiryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ListingExpiryServiceTest extends TestCase
{
    private ListingExpiryService $service;
    private $listingAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listingAlias = Mockery::mock('alias:' . Listing::class);
        $this->service = new ListingExpiryService();
    }

    public function test_processExpiredListings_returns_counts(): void
    {
        $this->listingAlias->shouldReceive('where')->andReturnSelf();
        $this->listingAlias->shouldReceive('whereNotNull')->andReturnSelf();
        $this->listingAlias->shouldReceive('select')->andReturnSelf();
        $this->listingAlias->shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->processExpiredListings();
        $this->assertSame(0, $result['expired']);
        $this->assertSame(0, $result['errors']);
    }

    public function test_processExpiredListings_handles_query_error(): void
    {
        $this->listingAlias->shouldReceive('where')->andThrow(new \Exception('Error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->processExpiredListings();
        $this->assertSame(0, $result['expired']);
        $this->assertSame(1, $result['errors']);
    }

    public function test_renewListing_not_found(): void
    {
        $this->listingAlias->shouldReceive('where')->andReturnSelf();
        $this->listingAlias->shouldReceive('first')->andReturn(null);

        $result = $this->service->renewListing(999, 1);
        $this->assertFalse($result['success']);
        $this->assertSame('Listing not found', $result['error']);
    }

    public function test_setExpiry_returns_bool(): void
    {
        $this->listingAlias->shouldReceive('where')->andReturnSelf();
        $this->listingAlias->shouldReceive('update')->andReturn(1);

        $this->assertTrue($this->service->setExpiry(1, '2027-01-01'));
    }

    public function test_setExpiry_handles_error(): void
    {
        $this->listingAlias->shouldReceive('where')->andThrow(new \Exception('Error'));
        Log::shouldReceive('error')->once();

        $this->assertFalse($this->service->setExpiry(1, '2027-01-01'));
    }
}
