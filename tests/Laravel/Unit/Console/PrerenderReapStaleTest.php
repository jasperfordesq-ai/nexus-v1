<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Services\PrerenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for prerender:reap-stale Artisan command.
 *
 * Uses unique tenant id 99719 for isolation.
 * PrerenderService is mocked for broadcastJob calls.
 * All DB writes are rolled back per test via DatabaseTransactions.
 *
 * The prerender_jobs table has no updated_at column, so handle() must NOT set
 * one in its UPDATE payloads — it writes only the columns that exist
 * (status / claimed_at / claimed_by / started_at / heartbeat_at when present /
 * finished_at / error_message).
 * The reap-path tests below assert the stuck rows are actually transitioned
 * (failed by default, queued under --requeue); the dry-run tests assert no
 * mutation occurs.
 */
class PrerenderReapStaleTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99719;

    /** @var \Mockery\MockInterface&PrerenderService */
    private \Mockery\MockInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Reap Stale Tenant',
            'slug'       => 'test-reap-stale-' . self::TENANT_ID,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        $this->service = Mockery::mock(PrerenderService::class);
        $this->service->shouldReceive('releaseJobLease')->zeroOrMoreTimes();
        $this->app->instance(PrerenderService::class, $this->service);
    }

    // -------------------------------------------------------------------------
    // Helper: insert a stuck prerender_jobs row using only real columns.
    // prerender_jobs has no created_at / updated_at columns.
    // -------------------------------------------------------------------------

    private function insertJob(string $status, Carbon $stuckAt, ?string $errorMessage = null): int
    {
        $data = [
            'tenant_id'     => self::TENANT_ID,
            'status'        => $status,
            'error_message' => $errorMessage,
            'queued_at'     => $stuckAt->toDateTimeString(),
        ];

        if ($status === 'claimed' || $status === 'running') {
            $data['claimed_at'] = $stuckAt->toDateTimeString();
            $data['claimed_by'] = 'test-host:12345';
        }

        if ($status === 'running') {
            $data['started_at'] = $stuckAt->toDateTimeString();
        }

        return (int) DB::table('prerender_jobs')->insertGetId($data);
    }

    // -------------------------------------------------------------------------
    // No stuck jobs → exits success, no DB mutation
    // -------------------------------------------------------------------------

    public function test_exits_success_when_no_stuck_jobs_exist(): void
    {
        // A fresh "queued" row should never be reaped.
        $id = $this->insertJob('queued', now()->subMinutes(2));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $this->assertSame('queued', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // Fresh claimed row (within threshold) → not reaped
    // -------------------------------------------------------------------------

    public function test_fresh_claimed_row_within_threshold_is_not_reaped(): void
    {
        // Only 5 minutes old; default claimed_minutes=10 → not stale.
        $id = $this->insertJob('claimed', now()->subMinutes(5));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $this->assertSame('claimed', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // Fresh running row (within threshold) → not reaped
    // -------------------------------------------------------------------------

    public function test_fresh_running_row_within_threshold_is_not_reaped(): void
    {
        // Only 10 minutes old; default running_minutes=45 → not stale.
        $id = $this->insertJob('running', now()->subMinutes(10));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $this->assertSame('running', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    public function test_recent_heartbeat_keeps_a_long_running_job_alive(): void
    {
        $id = $this->insertJob('running', now()->subHours(3));
        $leaseColumn = Schema::hasColumn('prerender_jobs', 'heartbeat_at')
            ? 'heartbeat_at'
            : 'started_at';
        DB::table('prerender_jobs')->where('id', $id)->update([
            $leaseColumn => now()->subMinute()->toDateTimeString(),
        ]);

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $this->assertSame('running', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // Dry-run: prints list but does not change rows
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_modify_stuck_claimed_row(): void
    {
        $id = $this->insertJob('claimed', now()->subMinutes(20));

        // broadcastJob must NOT be called in dry-run.
        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale', ['--dry-run' => true])
            ->assertExitCode(0);

        // Row must remain 'claimed' — dry-run never mutates.
        $this->assertSame('claimed', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // Dry-run: detects stale running row without modifying it
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_modify_stuck_running_row(): void
    {
        $id = $this->insertJob('running', now()->subMinutes(60));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame('running', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // Stale claimed row IS reaped: status -> failed, finished_at + error_message
    // set, and the job change is broadcast.
    // -------------------------------------------------------------------------

    public function test_stale_claimed_row_is_reaped_to_failed(): void
    {
        // 15-minute-old claimed row — well past the default 10-minute threshold.
        $id = $this->insertJob('claimed', now()->subMinutes(15));

        // Failed-reap path broadcasts the job change exactly once.
        $this->service->shouldReceive('broadcastJob')->once()->with($id);

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $row = DB::table('prerender_jobs')->where('id', $id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->finished_at);
        $this->assertSame('reaped: worker did not finalise within timeout', $row->error_message);
    }

    // -------------------------------------------------------------------------
    // Stale running row IS reaped to failed (similar to above).
    // -------------------------------------------------------------------------

    public function test_stale_running_row_is_reaped_to_failed(): void
    {
        $id = $this->insertJob('running', now()->subMinutes(60));

        $this->service->shouldReceive('broadcastJob')->once()->with($id);

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $row = DB::table('prerender_jobs')->where('id', $id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->finished_at);
        $this->assertSame('reaped: worker did not finalise within timeout', $row->error_message);
    }

    // -------------------------------------------------------------------------
    // Custom --claimed-minutes threshold: row below threshold → not touched
    // -------------------------------------------------------------------------

    public function test_custom_claimed_minutes_threshold_protects_recent_row(): void
    {
        // 3 min old; --claimed-minutes=10 → NOT stale.
        $id = $this->insertJob('claimed', now()->subMinutes(3));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale', ['--claimed-minutes' => '10'])
            ->assertExitCode(0);

        $this->assertSame('claimed', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // Custom --claimed-minutes threshold: row above threshold IS detected
    // -------------------------------------------------------------------------

    public function test_custom_claimed_minutes_threshold_detects_stale_row(): void
    {
        // 3 min old; --claimed-minutes=2 → stale.
        $id = $this->insertJob('claimed', now()->subMinutes(3));

        $this->service->shouldReceive('broadcastJob')->once()->with($id);

        $this->artisan('prerender:reap-stale', ['--claimed-minutes' => '2'])
            ->assertExitCode(0);

        $row = DB::table('prerender_jobs')->where('id', $id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->finished_at);
        $this->assertSame('reaped: worker did not finalise within timeout', $row->error_message);
    }

    // -------------------------------------------------------------------------
    // Dry-run: multiple stuck rows detected but none modified
    // -------------------------------------------------------------------------

    public function test_dry_run_detects_both_claimed_and_running_stuck_rows(): void
    {
        $claimedId = $this->insertJob('claimed', now()->subMinutes(15));
        $runningId = $this->insertJob('running', now()->subMinutes(60));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale', ['--dry-run' => true])
            ->assertExitCode(0);

        // Neither row should be mutated in dry-run.
        $this->assertSame('claimed', DB::table('prerender_jobs')->where('id', $claimedId)->value('status'));
        $this->assertSame('running', DB::table('prerender_jobs')->where('id', $runningId)->value('status'));
    }

    // -------------------------------------------------------------------------
    // --requeue dry-run: rows detected but not modified
    // -------------------------------------------------------------------------

    public function test_requeue_flag_in_dry_run_does_not_modify_rows(): void
    {
        $id = $this->insertJob('claimed', now()->subMinutes(20), null);

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale', ['--dry-run' => true, '--requeue' => true])
            ->assertExitCode(0);

        $this->assertSame('claimed', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }

    // -------------------------------------------------------------------------
    // --requeue (real run): a never-requeued stuck row is reset to queued and
    // its claim fields cleared. Requeue broadcasts so the admin UI updates.
    // -------------------------------------------------------------------------

    public function test_requeue_resets_stale_claimed_row_to_queued(): void
    {
        $id = $this->insertJob('claimed', now()->subMinutes(20), null);

        $this->service->shouldReceive('broadcastJob')->once()->with($id);

        $this->artisan('prerender:reap-stale', ['--requeue' => true])
            ->assertExitCode(0);

        $row = DB::table('prerender_jobs')->where('id', $id)->first();
        $this->assertSame('queued', $row->status);
        $this->assertNull($row->claimed_at);
        $this->assertNull($row->claimed_by);
        $this->assertNull($row->started_at);
        $this->assertSame('reaped: requeued once after stuck', $row->error_message);
    }

    // -------------------------------------------------------------------------
    // --requeue but the row was already requeued once (non-empty error_message)
    // → falls through to the failed path instead of looping forever.
    // -------------------------------------------------------------------------

    public function test_requeue_falls_back_to_failed_when_already_requeued(): void
    {
        $id = $this->insertJob('claimed', now()->subMinutes(20), 'reaped: requeued once after stuck');

        $this->service->shouldReceive('broadcastJob')->once()->with($id);

        $this->artisan('prerender:reap-stale', ['--requeue' => true])
            ->assertExitCode(0);

        $row = DB::table('prerender_jobs')->where('id', $id)->first();
        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->finished_at);
        $this->assertSame('reaped: worker did not finalise within timeout', $row->error_message);
    }

    // -------------------------------------------------------------------------
    // Succeeded/failed rows are not reaped (only claimed/running)
    // -------------------------------------------------------------------------

    public function test_completed_succeeded_row_is_not_reaped(): void
    {
        $id = $this->insertJob('succeeded', now()->subHours(2));

        $this->service->shouldNotReceive('broadcastJob');

        $this->artisan('prerender:reap-stale')
            ->assertExitCode(0);

        $this->assertSame('succeeded', DB::table('prerender_jobs')->where('id', $id)->value('status'));
    }
}
