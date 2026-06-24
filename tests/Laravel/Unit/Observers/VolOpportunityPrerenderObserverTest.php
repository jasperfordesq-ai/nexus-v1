<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\VolOpportunity;
use App\Observers\VolOpportunityPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VolOpportunityPrerenderObserverTest extends TestCase
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

    public function test_saved_invalidates_volunteering_index_and_opportunity_route(): void
    {
        $model = new VolOpportunity();
        $model->id        = 15;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/volunteering', $routes, true)
                    && in_array('/volunteering/opportunities/15', $routes, true);
            }), true);

        (new VolOpportunityPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // saved() — model without primary key → only /volunteering
    // -------------------------------------------------------------------------

    public function test_saved_omits_detail_route_when_model_has_no_key(): void
    {
        $model = new VolOpportunity();
        $model->tenant_id = 2;
        // id not set → getKey() returns null

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return $routes === ['/volunteering'];
            }), true);

        (new VolOpportunityPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted() — with primary key
    // -------------------------------------------------------------------------

    public function test_deleted_invalidates_volunteering_index_and_opportunity_route(): void
    {
        $model = new VolOpportunity();
        $model->id        = 22;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, Mockery::on(function (array $routes): bool {
                return in_array('/volunteering', $routes, true)
                    && in_array('/volunteering/opportunities/22', $routes, true);
            }), true);

        (new VolOpportunityPrerenderObserver())->deleted($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: null tenant_id → service not called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_null(): void
    {
        $model = new VolOpportunity();
        $model->id = 1;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new VolOpportunityPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Guard: tenant_id = 0 → service not called
    // -------------------------------------------------------------------------

    public function test_saved_skips_service_when_tenant_id_is_zero(): void
    {
        $model = new VolOpportunity();
        $model->id        = 2;
        $model->tenant_id = 0;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new VolOpportunityPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception swallowing
    // -------------------------------------------------------------------------

    public function test_saved_logs_warning_and_does_not_rethrow_when_service_throws(): void
    {
        $model = new VolOpportunity();
        $model->id        = 30;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->andThrow(new \RuntimeException('network error'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new VolOpportunityPrerenderObserver())->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Route format: nested /opportunities/ segment (not /volunteering/{id})
    // -------------------------------------------------------------------------

    public function test_saved_uses_correct_nested_opportunity_route_format(): void
    {
        $model = new VolOpportunity();
        $model->id        = 42;
        $model->tenant_id = 2;

        $captured = null;
        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andReturnUsing(function (int $tid, array $routes) use (&$captured) {
                $captured = $routes;
                return 1;
            });

        (new VolOpportunityPrerenderObserver())->saved($model);

        $this->assertContains('/volunteering', $captured);
        $this->assertContains('/volunteering/opportunities/42', $captured);
        // Must not contain a bare /volunteering/42 (wrong format)
        $this->assertNotContains('/volunteering/42', $captured);
    }
}
