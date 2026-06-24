<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\Page;
use App\Observers\PagePrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PagePrerenderObserverTest extends TestCase
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
    // saved() — with slug
    // -------------------------------------------------------------------------

    public function test_saved_invalidates_page_slug_route(): void
    {
        $model = new Page();
        $model->id        = 1;
        $model->tenant_id = 2;
        $model->slug      = 'about-us';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return $routes === ['/page/about-us'];
            }), true);

        (new PagePrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — no slug → routesFor returns [] → base class skips service call
    // -------------------------------------------------------------------------

    public function test_saved_does_not_call_service_when_slug_is_absent(): void
    {
        $model = new Page();
        $model->id        = 2;
        $model->tenant_id = 2;
        // slug intentionally unset

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new PagePrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — empty-string slug → treated same as absent
    // -------------------------------------------------------------------------

    public function test_saved_does_not_call_service_when_slug_is_empty_string(): void
    {
        $model = new Page();
        $model->id        = 3;
        $model->tenant_id = 2;
        $model->slug      = '';

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new PagePrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted() — with slug
    // -------------------------------------------------------------------------

    public function test_deleted_invalidates_page_slug_route(): void
    {
        $model = new Page();
        $model->id        = 4;
        $model->tenant_id = 2;
        $model->slug      = 'faq';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return $routes === ['/page/faq'];
            }), true);

        (new PagePrerenderObserver())->deleted($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: null tenant_id → service not called even with valid slug
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_null(): void
    {
        $model = new Page();
        $model->id   = 5;
        $model->slug = 'contact';

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new PagePrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception swallowing — errors must never surface to the caller
    // -------------------------------------------------------------------------

    public function test_saved_logs_warning_and_does_not_rethrow_when_service_throws(): void
    {
        $model = new Page();
        $model->id        = 6;
        $model->tenant_id = 2;
        $model->slug      = 'terms';

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->andThrow(new \RuntimeException('disk full'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new PagePrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Route format: slug embedded literally (no double-slash, no suffix)
    // -------------------------------------------------------------------------

    public function test_saved_builds_correct_route_string_for_slugged_page(): void
    {
        $model = new Page();
        $model->id        = 7;
        $model->tenant_id = 2;
        $model->slug      = 'community-guidelines';

        $captured = null;
        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andReturnUsing(function (int $tid, array $routes) use (&$captured) {
                $captured = $routes;
                return 1;
            });

        (new PagePrerenderObserver())->saved($model);

        $this->assertSame(['/page/community-guidelines'], $captured);
    }
}
