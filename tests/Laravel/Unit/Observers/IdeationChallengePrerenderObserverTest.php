<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Observers;

use App\Models\IdeationChallenge;
use App\Observers\IdeationChallengePrerenderObserver;
use App\Services\PrerenderService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for IdeationChallengePrerenderObserver.
 *
 * routesFor() returns ['/ideation'] plus '/ideation/{id}' when the model
 * has a primary key.
 */
class IdeationChallengePrerenderObserverTest extends TestCase
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
        $challenge = new IdeationChallenge();
        $challenge->id = 8;
        $challenge->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/ideation', '/ideation/8'], true);

        (new IdeationChallengePrerenderObserver())->saved($challenge);

        $this->assertTrue(true);
    }

    public function test_saved_enqueues_only_index_when_model_has_no_id(): void
    {
        $challenge = new IdeationChallenge();
        $challenge->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/ideation'], true);

        (new IdeationChallengePrerenderObserver())->saved($challenge);

        $this->assertTrue(true);
    }

    public function test_saved_routes_contain_correct_prefix(): void
    {
        $challenge = new IdeationChallenge();
        $challenge->id = 55;
        $challenge->tenant_id = 2;

        $capturedRoutes = null;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->withArgs(function (int $tid, array $routes, bool $enqueue) use (&$capturedRoutes): bool {
                $capturedRoutes = $routes;
                return true;
            });

        (new IdeationChallengePrerenderObserver())->saved($challenge);

        $this->assertContains('/ideation', $capturedRoutes);
        $this->assertContains('/ideation/55', $capturedRoutes);
    }

    public function test_saved_skips_when_tenant_id_is_null(): void
    {
        $challenge = new IdeationChallenge();
        $challenge->id = 3;
        $challenge->tenant_id = null;

        $this->prerenderMock->shouldNotReceive('invalidateRoutes');

        (new IdeationChallengePrerenderObserver())->saved($challenge);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deleted()
    // -------------------------------------------------------------------------

    public function test_deleted_enqueues_index_and_detail_routes(): void
    {
        $challenge = new IdeationChallenge();
        $challenge->id = 100;
        $challenge->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->with(2, ['/ideation', '/ideation/100'], true);

        (new IdeationChallengePrerenderObserver())->deleted($challenge);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception safety
    // -------------------------------------------------------------------------

    public function test_saved_swallows_exception_and_logs_warning(): void
    {
        $challenge = new IdeationChallenge();
        $challenge->id = 12;
        $challenge->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('connection refused'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new IdeationChallengePrerenderObserver())->saved($challenge);

        $this->assertTrue(true);
    }

    public function test_deleted_swallows_exception_and_logs_warning(): void
    {
        $challenge = new IdeationChallenge();
        $challenge->id = 13;
        $challenge->tenant_id = 2;

        $this->prerenderMock
            ->shouldReceive('invalidateRoutes')
            ->once()
            ->andThrow(new \RuntimeException('timeout'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Prerender invalidation failed', Mockery::type('array'));

        (new IdeationChallengePrerenderObserver())->deleted($challenge);

        $this->assertTrue(true);
    }
}
