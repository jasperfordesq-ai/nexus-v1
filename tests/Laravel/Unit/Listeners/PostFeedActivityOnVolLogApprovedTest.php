<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Listeners\PostFeedActivityOnVolLogApproved;
use App\Models\VolLog;
use App\Services\FeedActivityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for PostFeedActivityOnVolLogApproved listener.
 *
 * Strategy: mock FeedActivityService (injected via constructor) so we test the
 * listener logic without touching feed_activity DB directly. A real vol_log row
 * is inserted for the "happy path" tests that need VolLog::find() to return data.
 *
 * DatabaseTransactions rolls back all inserts after each test.
 */
class PostFeedActivityOnVolLogApprovedTest extends TestCase
{
    use DatabaseTransactions;

    private FeedActivityService $feedService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(2);
        $this->feedService = Mockery::mock(FeedActivityService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper: insert a minimal vol_log row for tenant 2 and return its id.
    // -------------------------------------------------------------------------
    private function insertVolLog(float $hours = 2.0, string $description = 'Helped at shelter'): int
    {
        // Find a real user in tenant 2 to satisfy the FK constraint.
        $userId = DB::table('users')->where('tenant_id', 2)->value('id');
        if (!$userId) {
            $this->markTestSkipped('No users found in tenant 2 — cannot create vol_log fixture.');
        }

        // Ensure the broadcast opt-out is ON (show_on_leaderboard = 1) so the
        // happy-path tests are deterministic regardless of seed data.
        DB::table('users')->where('id', $userId)->update(['show_on_leaderboard' => 1]);

        return (int) DB::table('vol_logs')->insertGetId([
            'tenant_id'   => 2,
            'user_id'     => $userId,
            'date_logged' => '2025-01-15',
            'hours'       => $hours,
            'description' => $description,
            'status'      => 'pending',
            'created_at'  => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 1: Happy path — pending → approved fires recordActivity
    // -------------------------------------------------------------------------
    public function test_handle_records_feed_activity_when_pending_to_approved(): void
    {
        $volLogId = $this->insertVolLog(3.0, 'Painted the community hall');

        $capturedArgs = [];
        $this->feedService
            ->shouldReceive('recordActivity')
            ->once()
            ->withArgs(function (int $tid, int $uid, string $type, int $sid, array $data) use (&$capturedArgs) {
                $capturedArgs = ['tid' => $tid, 'type' => $type, 'sid' => $sid, 'data' => $data];
                return true;
            });

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertSame(2, $capturedArgs['tid']);
        // 'volunteer_hours' (mapped to vol_logs), NOT 'volunteer' (vol_opportunities).
        $this->assertSame('volunteer_hours', $capturedArgs['type']);
        $this->assertSame($volLogId, $capturedArgs['sid']);
        $this->assertStringContainsString('3.00 hours', $capturedArgs['data']['title']);
        // Privacy: the volunteer's free-text description is NOT broadcast to the
        // community feed (it may disclose sensitive context written for the org).
        $this->assertNull($capturedArgs['data']['content']);
    }

    // -------------------------------------------------------------------------
    // Test: volunteer who opted out of public sharing (show_on_leaderboard = 0)
    // does NOT get their hours broadcast to the community feed.
    // -------------------------------------------------------------------------
    public function test_handle_respects_show_on_leaderboard_opt_out(): void
    {
        $volLogId = $this->insertVolLog(2.0, 'Sensitive context');
        $userId = (int) DB::table('vol_logs')->where('id', $volLogId)->value('user_id');
        DB::table('users')->where('id', $userId)->update(['show_on_leaderboard' => 0]);

        $this->feedService->shouldReceive('recordActivity')->never();

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 2: Title format includes hours with 2 decimal places
    // -------------------------------------------------------------------------
    public function test_handle_title_contains_hours_formatted_to_two_decimals(): void
    {
        $volLogId = $this->insertVolLog(1.5, '');

        $capturedData = null;
        $this->feedService
            ->shouldReceive('recordActivity')
            ->once()
            ->withArgs(function (int $tid, int $uid, string $type, int $sid, array $data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            });

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertIsArray($capturedData);
        $this->assertSame('Volunteered 1.50 hours', $capturedData['title']);
    }

    // -------------------------------------------------------------------------
    // Test 3: Null description becomes null content (not empty string)
    // -------------------------------------------------------------------------
    public function test_handle_null_description_produces_null_content(): void
    {
        $volLogId = $this->insertVolLog(2.0, '');

        // Manually set description to NULL for this test
        DB::table('vol_logs')->where('id', $volLogId)->update(['description' => null]);

        $capturedData = null;
        $this->feedService
            ->shouldReceive('recordActivity')
            ->once()
            ->withArgs(function (int $tid, int $uid, string $type, int $sid, array $data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            });

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertIsArray($capturedData);
        $this->assertNull($capturedData['content']);
    }

    // -------------------------------------------------------------------------
    // Test 4: No-op when newStatus is not 'approved'
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_new_status_is_not_approved(): void
    {
        $this->feedService->shouldReceive('recordActivity')->never();

        $event = new VolLogStatusChanged(2, 999, 'pending', 'declined');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertTrue(true); // assert no exception + no call
    }

    // -------------------------------------------------------------------------
    // Test 5: No-op when previousStatus is also 'approved' (re-approval guard)
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_already_was_approved(): void
    {
        $this->feedService->shouldReceive('recordActivity')->never();

        $event = new VolLogStatusChanged(2, 999, 'approved', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 6: No-op when vol_log row is not found
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_vol_log_not_found(): void
    {
        $this->feedService->shouldReceive('recordActivity')->never();

        $event = new VolLogStatusChanged(2, 99999999, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 7: No-op when hours <= 0
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_hours_zero_or_negative(): void
    {
        $volLogId = $this->insertVolLog(0.0, 'No hours');
        // Force hours to 0
        DB::table('vol_logs')->where('id', $volLogId)->update(['hours' => 0]);

        $this->feedService->shouldReceive('recordActivity')->never();

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 8: Exception from service is caught; warning is logged; no rethrow
    // -------------------------------------------------------------------------
    public function test_handle_catches_service_exception_and_logs_warning(): void
    {
        $volLogId = $this->insertVolLog(1.0, 'Some work');

        $this->feedService
            ->shouldReceive('recordActivity')
            ->once()
            ->andThrow(new \RuntimeException('DB unavailable'));

        Log::shouldReceive('warning')
            ->once()
            ->with('PostFeedActivityOnVolLogApproved failed', Mockery::type('array'));

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);

        // Must not throw
        $listener->handle($event);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 9: Metadata contains vol_log_id, hours, organization_id, opportunity_id
    // -------------------------------------------------------------------------
    public function test_handle_metadata_contains_expected_keys(): void
    {
        $volLogId = $this->insertVolLog(4.0, 'Tested metadata');

        $capturedData = null;
        $this->feedService
            ->shouldReceive('recordActivity')
            ->once()
            ->withArgs(function (int $tid, int $uid, string $type, int $sid, array $data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            });

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertIsArray($capturedData);
        $meta = $capturedData['metadata'];
        $this->assertArrayHasKey('vol_log_id', $meta);
        $this->assertArrayHasKey('hours', $meta);
        $this->assertArrayHasKey('organization_id', $meta);
        $this->assertArrayHasKey('opportunity_id', $meta);
        $this->assertSame($volLogId, $meta['vol_log_id']);
        $this->assertSame(4.0, $meta['hours']);
    }

    // -------------------------------------------------------------------------
    // Test 10: source_type passed to recordActivity is 'volunteer_hours'
    // -------------------------------------------------------------------------
    public function test_handle_uses_volunteer_source_type(): void
    {
        $volLogId = $this->insertVolLog(2.0, 'Community work');

        $capturedType = null;
        $this->feedService
            ->shouldReceive('recordActivity')
            ->once()
            ->withArgs(function (int $tid, int $uid, string $type, int $sid, array $data) use (&$capturedType) {
                $capturedType = $type;
                return true;
            });

        $event = new VolLogStatusChanged(2, $volLogId, 'pending', 'approved');
        $listener = new PostFeedActivityOnVolLogApproved($this->feedService);
        $listener->handle($event);

        $this->assertSame('volunteer_hours', $capturedType);
    }
}
