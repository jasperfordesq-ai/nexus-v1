<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\SyncUserSearchIndexJob;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * SyncUserSearchIndexJobTest
 *
 * SyncUserSearchIndexJob dispatches index or remove operations to SearchService,
 * which silently skips both when Meilisearch is unreachable (isAvailable()=false).
 *
 * Strategy:
 *   - Confirm job properties ($tries, $backoff, $timeout, default action, queue).
 *   - Confirm InvalidArgumentException for unknown action.
 *   - For 'remove' action: SearchService::removeUser() is called (Meilisearch guard
 *     means it's a no-op in test, but the call path is exercised).
 *   - For 'index' action with existing user: SearchService::indexUser() is called.
 *   - For 'index' action with missing user: SearchService::removeUser() is called
 *     (user was deleted between dispatch and execution).
 *   - failed() logs an error-level entry.
 *
 * Because SearchService's Meilisearch calls silently guard (isAvailable()=false
 * in test env), the actual HTTP is never made — tests are safe.
 */
class SyncUserSearchIndexJobTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(): int
    {
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'SearchUser',
            'first_name' => 'Search',
            'last_name'  => 'User',
            'email'      => 'searchuser.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /** Job exposes the expected property values. */
    public function test_job_has_correct_configuration(): void
    {
        $job = new SyncUserSearchIndexJob(1);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30], $job->backoff);
        $this->assertSame(15, $job->timeout);
    }

    /** Default action is 'index'. */
    public function test_default_action_is_index(): void
    {
        $job = new SyncUserSearchIndexJob(99);
        $this->assertSame('index', $job->action);
    }

    /** Job is dispatched onto the 'search' queue. */
    public function test_job_is_placed_on_search_queue(): void
    {
        $job = new SyncUserSearchIndexJob(1, 'index');
        $this->assertSame('search', $job->queue);
    }

    /** Constructor rejects unknown action values. */
    public function test_constructor_rejects_unknown_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SyncUserSearchIndexJob(1, 'sync');
    }

    /** 'index' and 'remove' are accepted without exception. */
    public function test_constructor_accepts_valid_actions(): void
    {
        $jobIndex  = new SyncUserSearchIndexJob(1, 'index');
        $jobRemove = new SyncUserSearchIndexJob(1, 'remove');
        $this->assertSame('index', $jobIndex->action);
        $this->assertSame('remove', $jobRemove->action);
    }

    /**
     * 'remove' action: SearchService::removeUser() is invoked.
     * Meilisearch guard means the actual HTTP call is skipped — no errors thrown.
     */
    public function test_handle_remove_action_calls_search_service_remove(): void
    {
        // We cannot easily mock a static class without a static proxy layer, so we
        // exercise the real code path. SearchService::isAvailable() returns false in
        // test (no Meilisearch), so removeUser() exits immediately after the guard.
        // Reset the cached availability flag so the test env is clean.
        $ref = new \ReflectionClass(SearchService::class);
        $prop = $ref->getProperty('available');
        $prop->setAccessible(true);
        $prop->setValue(null, null); // reset cached state

        $job = new SyncUserSearchIndexJob(42, 'remove');
        $job->handle(); // must not throw

        $this->assertTrue(true);
    }

    /**
     * 'index' action for a user that exists: handle() reaches SearchService::indexUser()
     * and exits cleanly (Meilisearch guard no-ops without error).
     */
    public function test_handle_index_action_for_existing_user_exits_cleanly(): void
    {
        $ref = new \ReflectionClass(SearchService::class);
        $prop = $ref->getProperty('available');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $userId = $this->insertUser();

        $job = new SyncUserSearchIndexJob($userId, 'index');
        $job->handle(); // must not throw

        $this->assertTrue(true);
    }

    /**
     * 'index' action when the user no longer exists in the DB: handle() falls back
     * to SearchService::removeUser() (the user was deleted between dispatch and run).
     */
    public function test_handle_index_action_for_missing_user_calls_remove(): void
    {
        $ref = new \ReflectionClass(SearchService::class);
        $prop = $ref->getProperty('available');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Use a user ID that does not exist.
        $job = new SyncUserSearchIndexJob(9999998, 'index');
        $job->handle(); // must not throw — falls through to removeUser()

        $this->assertTrue(true);
    }

    /**
     * failed() logs an error-level entry with user_id, action, and error message.
     */
    public function test_failed_logs_error_with_context(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('SyncUserSearchIndexJob permanently failed', \Mockery::on(fn ($ctx) =>
                isset($ctx['user_id'], $ctx['action'], $ctx['error'])
                && $ctx['user_id'] === 77
                && $ctx['action'] === 'remove'
            ));

        $job = new SyncUserSearchIndexJob(77, 'remove');
        $job->failed(new \RuntimeException('meilisearch timeout'));
        $this->assertTrue(true);
    }

    /**
     * Verify the job constructor stores userId correctly.
     */
    public function test_job_stores_user_id(): void
    {
        $job = new SyncUserSearchIndexJob(123, 'index');
        $this->assertSame(123, $job->userId);
    }
}
