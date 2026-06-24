<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\MarketplaceListing;
use App\Observers\MarketplaceListingPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MarketplaceListingPrerenderObserverTest extends TestCase
{
    /** @var \Mockery\MockInterface */
    private $prerenderMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prerenderMock = Mockery::mock(PrerenderService::class);
        $this->app->instance(PrerenderService::class, $this->prerenderMock);
    }

    // -------------------------------------------------------------------------
    // saved() — model with a primary key
    // -------------------------------------------------------------------------

    public function test_saved_invalidates_index_routes_and_detail_route(): void
    {
        $model = new MarketplaceListing();
        $model->id        = 42;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/marketplace', $routes, true)
                    && in_array('/marketplace/free', $routes, true)
                    && in_array('/marketplace/map', $routes, true)
                    && in_array('/marketplace/42', $routes, true);
            }), true);

        (new MarketplaceListingPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — model WITHOUT a primary key (new, unsaved — getKey() = null)
    // -------------------------------------------------------------------------

    public function test_saved_omits_detail_route_when_model_has_no_key(): void
    {
        $model = new MarketplaceListing();
        $model->tenant_id = 2;
        // id intentionally not set → getKey() returns null

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/marketplace', $routes, true)
                    && in_array('/marketplace/free', $routes, true)
                    && in_array('/marketplace/map', $routes, true)
                    && !in_array('/marketplace/', $routes, true);
            }), true);

        (new MarketplaceListingPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted() — with key
    // -------------------------------------------------------------------------

    public function test_deleted_invalidates_all_expected_routes(): void
    {
        $model = new MarketplaceListing();
        $model->id        = 7;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/marketplace', $routes, true)
                    && in_array('/marketplace/free', $routes, true)
                    && in_array('/marketplace/map', $routes, true)
                    && in_array('/marketplace/7', $routes, true);
            }), true);

        (new MarketplaceListingPrerenderObserver())->deleted($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: null tenant_id → service not called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_null(): void
    {
        $model = new MarketplaceListing();
        $model->id = 10;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new MarketplaceListingPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: tenant_id = 0 → service not called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_zero(): void
    {
        $model = new MarketplaceListing();
        $model->id        = 11;
        $model->tenant_id = 0;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new MarketplaceListingPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception swallowing
    // -------------------------------------------------------------------------

    public function test_saved_logs_warning_and_does_not_rethrow_when_service_throws(): void
    {
        $model = new MarketplaceListing();
        $model->id        = 12;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->andThrow(new \RuntimeException('timeout'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new MarketplaceListingPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Route count sanity — always at least 3 index routes
    // -------------------------------------------------------------------------

    public function test_saved_always_includes_all_three_index_routes(): void
    {
        $model = new MarketplaceListing();
        $model->id        = 99;
        $model->tenant_id = 2;

        $captured = null;
        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andReturnUsing(function (int $tid, array $routes) use (&$captured) {
                $captured = $routes;
                return 1;
            });

        (new MarketplaceListingPrerenderObserver())->saved($model);

        $this->assertContains('/marketplace', $captured);
        $this->assertContains('/marketplace/free', $captured);
        $this->assertContains('/marketplace/map', $captured);
    }
}
