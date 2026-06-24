<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\JobVacancy;
use App\Observers\JobVacancyPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for JobVacancyPrerenderObserver.
 *
 * routesFor() returns ['/jobs'] plus '/jobs/{id}' when the model has a
 * primary key.
 */
class JobVacancyPrerenderObserverTest extends TestCase
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
        $vacancy = new JobVacancy();
        $vacancy->id = 17;
        $vacancy->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/jobs', '/jobs/17'], true);

        (new JobVacancyPrerenderObserver())->saved($vacancy);

        $this->assertTrue(true);
    }

    public function test_saved_enqueues_only_index_when_model_has_no_id(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/jobs'], true);

        (new JobVacancyPrerenderObserver())->saved($vacancy);

        $this->assertTrue(true);
    }

    public function test_saved_detail_route_includes_correct_id(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->id = 999;
        $vacancy->tenant_id = 2;

        $capturedRoutes = null;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->withArgs(function (int $tid, array $routes, bool $enqueue) use (&$capturedRoutes): bool {
                $capturedRoutes = $routes;
                return true;
            });

        (new JobVacancyPrerenderObserver())->saved($vacancy);

        $this->assertContains('/jobs/999', $capturedRoutes);
    }

    public function test_saved_skips_when_tenant_id_is_null(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->id = 4;
        $vacancy->tenant_id = null;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new JobVacancyPrerenderObserver())->saved($vacancy);

        $this->assertTrue(true);
    }

    public function test_saved_skips_when_tenant_id_is_zero(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->id = 6;
        $vacancy->tenant_id = 0;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new JobVacancyPrerenderObserver())->saved($vacancy);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted()
    // -------------------------------------------------------------------------

    public function test_deleted_enqueues_index_and_detail_routes(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->id = 50;
        $vacancy->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/jobs', '/jobs/50'], true);

        (new JobVacancyPrerenderObserver())->deleted($vacancy);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception safety
    // -------------------------------------------------------------------------

    public function test_saved_swallows_exception_and_logs_warning(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->id = 77;
        $vacancy->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('db locked'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new JobVacancyPrerenderObserver())->saved($vacancy);

        $this->assertTrue(true);
    }

    public function test_deleted_swallows_exception_and_logs_warning(): void
    {
        $vacancy = new JobVacancy();
        $vacancy->id = 78;
        $vacancy->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('connection lost'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new JobVacancyPrerenderObserver())->deleted($vacancy);

        $this->assertTrue(true);
    }
}
