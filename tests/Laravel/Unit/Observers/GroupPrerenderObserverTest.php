<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\Group;
use App\Observers\GroupPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for GroupPrerenderObserver.
 *
 * routesFor() returns ['/groups'] plus '/groups/{id}' when the model has a
 * primary key.  Invalidation of private groups is idempotent (the snapshot
 * won't exist) — the observer still attempts it.
 */
class GroupPrerenderObserverTest extends TestCase
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

    public function test_saved_enqueues_index_and_detail_routes_when_model_has_id(): void
    {
        $group = new Group();
        $group->id = 15;
        $group->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/groups', '/groups/15'], true);

        (new GroupPrerenderObserver())->saved($group);

        $this->assertTrue(true);
    }

    public function test_saved_enqueues_only_index_when_model_has_no_id(): void
    {
        $group = new Group();
        $group->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/groups'], true);

        (new GroupPrerenderObserver())->saved($group);

        $this->assertTrue(true);
    }

    public function test_saved_skips_when_tenant_id_is_null(): void
    {
        $group = new Group();
        $group->id = 1;
        $group->tenant_id = null;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new GroupPrerenderObserver())->saved($group);

        $this->assertTrue(true);
    }

    public function test_saved_skips_when_tenant_id_is_zero(): void
    {
        $group = new Group();
        $group->id = 2;
        $group->tenant_id = 0;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new GroupPrerenderObserver())->saved($group);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted()
    // -------------------------------------------------------------------------

    public function test_deleted_enqueues_index_and_detail_routes(): void
    {
        $group = new Group();
        $group->id = 30;
        $group->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/groups', '/groups/30'], true);

        (new GroupPrerenderObserver())->deleted($group);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception safety
    // -------------------------------------------------------------------------

    public function test_saved_swallows_exception_and_logs_warning(): void
    {
        $group = new Group();
        $group->id = 20;
        $group->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('network error'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new GroupPrerenderObserver())->saved($group);

        $this->assertTrue(true);
    }

    public function test_deleted_swallows_exception_and_logs_warning(): void
    {
        $group = new Group();
        $group->id = 21;
        $group->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('db gone'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new GroupPrerenderObserver())->deleted($group);

        $this->assertTrue(true);
    }
}
