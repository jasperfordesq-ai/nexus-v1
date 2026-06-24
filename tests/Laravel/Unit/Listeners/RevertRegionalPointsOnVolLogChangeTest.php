<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Listeners\RevertRegionalPointsOnVolLogChange;
use App\Services\CaringCommunity\CaringRegionalPointService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for RevertRegionalPointsOnVolLogChange listener.
 *
 * The listener delegates all point-reversal logic to
 * CaringRegionalPointService::reverseFromVolLog(). We mock the service and
 * assert the listener's own branching / error-handling logic.
 *
 * DatabaseTransactions is included so any accidental real DB writes roll back.
 */
class RevertRegionalPointsOnVolLogChangeTest extends TestCase
{
    use DatabaseTransactions;

    private CaringRegionalPointService $regionalPointService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(2);
        $this->regionalPointService = Mockery::mock(CaringRegionalPointService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Test 1: Happy path — approved → declined triggers reverseFromVolLog
    // -------------------------------------------------------------------------
    public function test_handle_reverses_points_when_leaving_approved_to_declined(): void
    {
        $capturedId = null;
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->withArgs(function (int $id, string $reason) use (&$capturedId) {
                $capturedId = $id;
                return true;
            })
            ->andReturn(true);

        $event = new VolLogStatusChanged(2, 42, 'approved', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertSame(42, $capturedId);
    }

    // -------------------------------------------------------------------------
    // Test 2: approved → pending also triggers reversal
    // -------------------------------------------------------------------------
    public function test_handle_reverses_points_when_leaving_approved_to_pending(): void
    {
        $capturedId = null;
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->withArgs(function (int $id, string $reason) use (&$capturedId) {
                $capturedId = $id;
                return true;
            })
            ->andReturn(true);

        $event = new VolLogStatusChanged(2, 55, 'approved', 'pending');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertSame(55, $capturedId);
    }

    // -------------------------------------------------------------------------
    // Test 3: Reason string contains both previous and new status
    // -------------------------------------------------------------------------
    public function test_handle_reason_contains_status_transition(): void
    {
        $capturedReason = null;
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->withArgs(function (int $id, string $reason) use (&$capturedReason) {
                $capturedReason = $reason;
                return true;
            })
            ->andReturn(true);

        $event = new VolLogStatusChanged(2, 10, 'approved', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertIsString($capturedReason);
        $this->assertStringContainsString('approved', $capturedReason);
        $this->assertStringContainsString('declined', $capturedReason);
    }

    // -------------------------------------------------------------------------
    // Test 4: No-op when previousStatus is NOT 'approved' (e.g. pending → declined)
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_previous_status_not_approved(): void
    {
        $this->regionalPointService->shouldReceive('reverseFromVolLog')->never();

        $event = new VolLogStatusChanged(2, 42, 'pending', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 5: No-op when approved → approved (no actual status change away from approved)
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_new_status_still_approved(): void
    {
        $this->regionalPointService->shouldReceive('reverseFromVolLog')->never();

        // previousStatus === newStatus === 'approved' → guard blocks it
        $event = new VolLogStatusChanged(2, 42, 'approved', 'approved');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 6: No-op when previousStatus is something other than approved (declined → pending)
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_transitioning_from_declined(): void
    {
        $this->regionalPointService->shouldReceive('reverseFromVolLog')->never();

        $event = new VolLogStatusChanged(2, 42, 'declined', 'pending');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 7: Exception from service is caught; warning is logged; no rethrow
    // -------------------------------------------------------------------------
    public function test_handle_catches_service_exception_and_logs_warning(): void
    {
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->andThrow(new \RuntimeException('Database locked'));

        Log::shouldReceive('warning')
            ->once()
            ->with('RevertRegionalPointsOnVolLogChange failed', Mockery::type('array'));

        $event = new VolLogStatusChanged(2, 77, 'approved', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);

        // Must not throw
        $listener->handle($event);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 8: Error context includes tenant_id, vol_log_id, statuses
    // -------------------------------------------------------------------------
    public function test_handle_warning_log_contains_context_keys(): void
    {
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->andThrow(new \RuntimeException('Oops'));

        $capturedCtx = null;
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use (&$capturedCtx) {
                $capturedCtx = $ctx;
                return true;
            });

        $event = new VolLogStatusChanged(2, 88, 'approved', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertIsArray($capturedCtx);
        $this->assertArrayHasKey('tenant_id', $capturedCtx);
        $this->assertArrayHasKey('vol_log_id', $capturedCtx);
        $this->assertArrayHasKey('previous_status', $capturedCtx);
        $this->assertArrayHasKey('new_status', $capturedCtx);
        $this->assertArrayHasKey('error', $capturedCtx);
    }

    // -------------------------------------------------------------------------
    // Test 9: vol_log_id passed to reverseFromVolLog matches event's volLogId
    // -------------------------------------------------------------------------
    public function test_handle_passes_correct_vol_log_id_to_service(): void
    {
        $capturedId = null;
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->withArgs(function (int $id, string $reason) use (&$capturedId) {
                $capturedId = $id;
                return true;
            })
            ->andReturn(true);

        $event = new VolLogStatusChanged(2, 999, 'approved', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);
        $listener->handle($event);

        $this->assertSame(999, $capturedId);
    }

    // -------------------------------------------------------------------------
    // Test 10: Service returning false (no prior award) is handled gracefully
    // -------------------------------------------------------------------------
    public function test_handle_gracefully_accepts_false_return_from_service(): void
    {
        $this->regionalPointService
            ->shouldReceive('reverseFromVolLog')
            ->once()
            ->andReturn(false);

        $event = new VolLogStatusChanged(2, 123, 'approved', 'declined');
        $listener = new RevertRegionalPointsOnVolLogChange($this->regionalPointService);

        // No exception expected; false return = no prior award, that's fine
        $listener->handle($event);
        $this->assertTrue(true);
    }
}
