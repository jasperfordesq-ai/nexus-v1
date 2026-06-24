<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\MarketplaceCategory;
use App\Observers\MarketplaceCategoryPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MarketplaceCategoryPrerenderObserverTest extends TestCase
{
    /** @var \Mockery\MockInterface */
    private $prerenderMock;

    protected function setUp(): void
    {
        parent::setUp();
        // The observer calls app(PrerenderService::class), so we bind the mock
        // into the IoC container. Mockery::mock() without alias: works here.
        $this->prerenderMock = Mockery::mock(PrerenderService::class);
        $this->app->instance(PrerenderService::class, $this->prerenderMock);
    }

    // -------------------------------------------------------------------------
    // saved() — with slug
    // -------------------------------------------------------------------------

    public function test_saved_invalidates_index_and_category_route_when_slug_present(): void
    {
        $model = new MarketplaceCategory();
        $model->id        = 3;
        $model->tenant_id = 2;
        $model->slug      = 'tools';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/marketplace', $routes, true)
                    && in_array('/marketplace/category/tools', $routes, true);
            }), true);

        (new MarketplaceCategoryPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — without slug
    // -------------------------------------------------------------------------

    public function test_saved_invalidates_only_index_when_slug_absent(): void
    {
        $model = new MarketplaceCategory();
        $model->id        = 5;
        $model->tenant_id = 2;
        // slug intentionally left unset

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return $routes === ['/marketplace'];
            }), true);

        (new MarketplaceCategoryPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — empty-string slug treated as absent
    // -------------------------------------------------------------------------

    public function test_saved_omits_category_route_when_slug_is_empty_string(): void
    {
        $model = new MarketplaceCategory();
        $model->id        = 7;
        $model->tenant_id = 2;
        $model->slug      = '';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return $routes === ['/marketplace'];
            }), true);

        (new MarketplaceCategoryPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted() — with slug
    // -------------------------------------------------------------------------

    public function test_deleted_invalidates_index_and_category_route(): void
    {
        $model = new MarketplaceCategory();
        $model->id        = 3;
        $model->tenant_id = 2;
        $model->slug      = 'books';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/marketplace', $routes, true)
                    && in_array('/marketplace/category/books', $routes, true);
            }), true);

        (new MarketplaceCategoryPrerenderObserver())->deleted($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: null tenant_id → service is never called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_null(): void
    {
        $model = new MarketplaceCategory();
        $model->id   = 1;
        $model->slug = 'electronics';
        // tenant_id intentionally not set

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new MarketplaceCategoryPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: tenant_id = 0 → service is never called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_zero(): void
    {
        $model = new MarketplaceCategory();
        $model->id        = 1;
        $model->tenant_id = 0;
        $model->slug      = 'electronics';

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new MarketplaceCategoryPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception swallowing: service error is logged, model write proceeds
    // -------------------------------------------------------------------------

    public function test_saved_logs_warning_and_does_not_rethrow_when_service_throws(): void
    {
        $model = new MarketplaceCategory();
        $model->id        = 9;
        $model->tenant_id = 2;
        $model->slug      = 'garden';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->andThrow(new \RuntimeException('prerender down'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        // Must not throw
        (new MarketplaceCategoryPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }
}
