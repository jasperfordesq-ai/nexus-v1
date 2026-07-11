<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\ResourceItem;
use App\Observers\ResourceItemPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * NOTE: ResourceItem's Eloquent model maps to the `resources` table (not
 * `resource_items` which has only id + created_at and no tenant_id column).
 * The observer receives the model instance bound in the app — we test route-
 * building logic independently of the DB table.
 */
class ResourceItemPrerenderObserverTest extends TestCase
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
    // saved() — with primary key
    // -------------------------------------------------------------------------

    public function test_saved_invalidates_only_resources_index(): void
    {
        $model = new ResourceItem();
        $model->id        = 5;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/resources'], true);

        (new ResourceItemPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — no primary key → only /resources
    // -------------------------------------------------------------------------

    public function test_saved_omits_detail_route_when_model_has_no_key(): void
    {
        $model = new ResourceItem();
        $model->tenant_id = 2;
        // id not set → getKey() returns null

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return $routes === ['/resources'];
            }), true);

        (new ResourceItemPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted() — with primary key
    // -------------------------------------------------------------------------

    public function test_deleted_invalidates_only_resources_index(): void
    {
        $model = new ResourceItem();
        $model->id        = 8;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/resources'], true);

        (new ResourceItemPrerenderObserver())->deleted($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: null tenant_id → service not called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_null(): void
    {
        $model = new ResourceItem();
        $model->id = 3;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new ResourceItemPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: tenant_id = 0 → service not called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_zero(): void
    {
        $model = new ResourceItem();
        $model->id        = 4;
        $model->tenant_id = 0;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new ResourceItemPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception swallowing
    // -------------------------------------------------------------------------

    public function test_saved_logs_warning_and_does_not_rethrow_when_service_throws(): void
    {
        $model = new ResourceItem();
        $model->id        = 9;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->andThrow(new \RuntimeException('service unavailable'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new ResourceItemPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Unsupported detail routes are never submitted for invalidation
    // -------------------------------------------------------------------------

    public function test_saved_does_not_submit_nonexistent_detail_route(): void
    {
        $model = new ResourceItem();
        $model->id        = 17;
        $model->tenant_id = 2;

        $captured = null;
        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andReturnUsing(function (int $tid, array $routes) use (&$captured) {
                $captured = $routes;
                return 1;
            });

        (new ResourceItemPrerenderObserver())->saved($model);

        $this->assertSame(['/resources'], $captured);
    }
}
