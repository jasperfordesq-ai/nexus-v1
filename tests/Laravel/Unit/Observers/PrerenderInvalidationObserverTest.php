<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Observers\PrerenderInvalidationObserver;
use App\Services\PrerenderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests the PrerenderInvalidationObserver abstract base class through a minimal
 * anonymous concrete subclass that implements routesFor().  All real behaviour
 * lives in the base class; the concrete prerender observers are each tested in
 * their own file.
 */
class PrerenderInvalidationObserverTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private \Mockery\MockInterface $prerenderMock;

    /** Tiny concrete subclass for exercising the base class in isolation. */
    private function makeObserver(array $routesToReturn): PrerenderInvalidationObserver
    {
        return new class($routesToReturn) extends PrerenderInvalidationObserver {
            public function __construct(private readonly array $routes) {}

            protected function routesFor(Model $model): array
            {
                return $this->routes;
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->prerenderMock = Mockery::mock(PrerenderService::class);
        $this->app->instance(PrerenderService::class, $this->prerenderMock);
    }

    // -------------------------------------------------------------------------
    // saved()
    // -------------------------------------------------------------------------

    public function test_saved_calls_invalidate_routes_with_correct_tenant_and_routes(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
            protected $primaryKey = 'id';
        };
        $model->id = 10;
        $model->tenant_id = 2;

        $routes = ['/test', '/test/10'];

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, $routes, true);

        $this->makeObserver($routes)->saved($model);

        // Assertion is the mock expectation; add a guard so phpunit counts it.
        $this->assertTrue(true);
    }

    public function test_saved_with_single_index_route_enqueues_index_only(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 99;
        $model->tenant_id = 2;

        $routes = ['/things'];

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/things'], true);

        $this->makeObserver(['/things'])->saved($model);

        $this->assertTrue(true);
    }

    public function test_saved_skips_dispatch_when_tenant_id_is_zero(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 5;
        $model->tenant_id = 0;

        // Observer must NOT call PrerenderService when tenant_id === 0.
        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        $this->makeObserver(['/test'])->saved($model);

        $this->assertTrue(true);
    }

    public function test_saved_skips_dispatch_when_tenant_id_is_null(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 5;
        $model->tenant_id = null;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        $this->makeObserver(['/test'])->saved($model);

        $this->assertTrue(true);
    }

    public function test_saved_skips_dispatch_when_routes_array_is_empty(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 3;
        $model->tenant_id = 2;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        $this->makeObserver([])->saved($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted()
    // -------------------------------------------------------------------------

    public function test_deleted_calls_invalidate_routes(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 7;
        $model->tenant_id = 2;

        $routes = ['/things', '/things/7'];

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, $routes, true);

        $this->makeObserver($routes)->deleted($model);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception swallowing
    // -------------------------------------------------------------------------

    public function test_saved_swallows_exception_and_logs_warning(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 11;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('disk full'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        $this->makeObserver(['/test'])->saved($model);

        $this->assertTrue(true);
    }

    public function test_deleted_swallows_exception_and_logs_warning(): void
    {
        $model = new class extends Model {
            protected $table = 'events';
        };
        $model->id = 12;
        $model->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('timeout'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        $this->makeObserver(['/test'])->deleted($model);

        $this->assertTrue(true);
    }
}
