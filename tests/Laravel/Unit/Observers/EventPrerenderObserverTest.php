<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\Event;
use App\Observers\EventPrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for EventPrerenderObserver.
 *
 * The observer extends PrerenderInvalidationObserver and returns ['/events']
 * plus '/events/{id}' when the model has a primary key.
 */
class EventPrerenderObserverTest extends TestCase
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
        $event = new Event();
        $event->id = 42;
        $event->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/events', '/events/42'], true);

        (new EventPrerenderObserver())->saved($event);

        $this->assertTrue(true);
    }

    public function test_saved_enqueues_only_index_when_model_has_no_id(): void
    {
        $event = new Event();
        // id is null — no detail route expected
        $event->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/events'], true);

        (new EventPrerenderObserver())->saved($event);

        $this->assertTrue(true);
    }

    public function test_saved_passes_enqueue_recache_true(): void
    {
        // The third argument to invalidateRoutes must always be true from observers
        // so the prerender worker will re-render after invalidation.
        $event = new Event();
        $event->id = 1;
        $event->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->withArgs(function (int $tid, array $routes, bool $enqueue): bool {
                return $tid === 2 && $enqueue === true;
            });

        (new EventPrerenderObserver())->saved($event);

        $this->assertTrue(true);
    }

    public function test_saved_skips_when_tenant_id_is_null(): void
    {
        $event = new Event();
        $event->id = 5;
        $event->tenant_id = null;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new EventPrerenderObserver())->saved($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted()
    // -------------------------------------------------------------------------

    public function test_deleted_enqueues_index_and_detail_routes(): void
    {
        $event = new Event();
        $event->id = 99;
        $event->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/events', '/events/99'], true);

        (new EventPrerenderObserver())->deleted($event);

        $this->assertTrue(true);
    }

    public function test_deleted_uses_correct_tenant_id(): void
    {
        $event = new Event();
        $event->id = 3;
        $event->tenant_id = 2;

        $capturedTenantId = null;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->withArgs(function (int $tid, array $routes, bool $enqueue) use (&$capturedTenantId): bool {
                $capturedTenantId = $tid;
                return true;
            });

        (new EventPrerenderObserver())->deleted($event);

        $this->assertSame(2, $capturedTenantId);
    }

    // -------------------------------------------------------------------------
    // Exception safety
    // -------------------------------------------------------------------------

    public function test_saved_swallows_exception_so_model_write_is_not_blocked(): void
    {
        $event = new Event();
        $event->id = 7;
        $event->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('service down'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        // Must not throw.
        (new EventPrerenderObserver())->saved($event);

        $this->assertTrue(true);
    }

    public function test_deleted_swallows_exception(): void
    {
        $event = new Event();
        $event->id = 8;
        $event->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new EventPrerenderObserver())->deleted($event);

        $this->assertTrue(true);
    }
}
