<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\Listing;
use App\Observers\ListingPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for ListingPrerenderObserver.
 *
 * routesFor() returns ['/listings'] plus '/listings/{id}' when the model
 * has a primary key.
 */
class ListingPrerenderObserverTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private \Mockery\MockInterface $prerenderMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prerenderMock = Mockery::mock(PrerenderService::class);
        $this->app->instance(PrerenderService::class, $this->prerenderMock);
    }

    // -------------------------------------------------------------------------
    // saved()
    // -------------------------------------------------------------------------

    public function test_saved_enqueues_index_and_detail_routes(): void
    {
        $listing = new Listing();
        $listing->id = 33;
        $listing->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/listings', '/listings/33'], true);

        (new ListingPrerenderObserver())->saved($listing);

        $this->assertTrue(true);
    }

    public function test_saved_enqueues_only_index_when_model_has_no_id(): void
    {
        $listing = new Listing();
        $listing->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/listings'], true);

        (new ListingPrerenderObserver())->saved($listing);

        $this->assertTrue(true);
    }

    public function test_saved_routes_start_with_slash_listings(): void
    {
        $listing = new Listing();
        $listing->id = 200;
        $listing->tenant_id = 2;

        $capturedRoutes = null;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->withArgs(function (int $tid, array $routes, bool $enqueue) use (&$capturedRoutes): bool {
                $capturedRoutes = $routes;
                return true;
            });

        (new ListingPrerenderObserver())->saved($listing);

        foreach ($capturedRoutes as $route) {
            $this->assertStringStartsWith('/listings', $route);
        }
    }

    public function test_saved_skips_when_tenant_id_is_null(): void
    {
        $listing = new Listing();
        $listing->id = 10;
        $listing->tenant_id = null;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new ListingPrerenderObserver())->saved($listing);

        $this->assertTrue(true);
    }

    public function test_saved_skips_when_tenant_id_is_zero(): void
    {
        $listing = new Listing();
        $listing->id = 11;
        $listing->tenant_id = 0;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new ListingPrerenderObserver())->saved($listing);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted()
    // -------------------------------------------------------------------------

    public function test_deleted_enqueues_index_and_detail_routes(): void
    {
        $listing = new Listing();
        $listing->id = 77;
        $listing->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/listings', '/listings/77'], true);

        (new ListingPrerenderObserver())->deleted($listing);

        $this->assertTrue(true);
    }

    public function test_deleted_passes_enqueue_recache_true(): void
    {
        $listing = new Listing();
        $listing->id = 78;
        $listing->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->withArgs(function (int $tid, array $routes, bool $enqueue): bool {
                return $enqueue === true;
            });

        (new ListingPrerenderObserver())->deleted($listing);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception safety
    // -------------------------------------------------------------------------

    public function test_saved_swallows_exception_and_logs_warning(): void
    {
        $listing = new Listing();
        $listing->id = 44;
        $listing->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('prerender service unavailable'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new ListingPrerenderObserver())->saved($listing);

        $this->assertTrue(true);
    }

    public function test_deleted_swallows_exception_and_logs_warning(): void
    {
        $listing = new Listing();
        $listing->id = 45;
        $listing->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('i/o error'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new ListingPrerenderObserver())->deleted($listing);

        $this->assertTrue(true);
    }
}
