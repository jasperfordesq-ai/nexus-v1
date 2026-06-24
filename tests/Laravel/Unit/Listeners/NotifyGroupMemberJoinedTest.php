<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\GroupMemberJoined;
use App\Listeners\NotifyGroupMemberJoined;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifyGroupMemberJoined listener.
 *
 * The listener fetches the group owner from groups.owner_id and notifies
 * them (via NotificationDispatcher::dispatch) when a different user joins.
 * Owner joining their own group is silently skipped.
 *
 * Strategy: real DB inserts (rolled back by DatabaseTransactions) +
 * alias mock on NotificationDispatcher to avoid email/push side effects.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyGroupMemberJoinedTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    private $dispatcherAlias;

    /** Fixture IDs inserted per test. */
    private int $ownerId;
    private int $joinerId;
    private int $groupId;

    protected function setUp(): void
    {
        // Alias mock MUST be created before parent::setUp() (app boot).
        $this->dispatcherAlias = Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldIgnoreMissing();

        parent::setUp();

        Cache::flush();

        // Insert fixture owner and joiner users for tenant 2.
        $this->ownerId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => 2,
            'name'               => 'Group Owner',
            'first_name'         => 'Group',
            'last_name'          => 'Owner',
            'email'              => 'gmj_owner_test@example.com',
            'password'           => 'x',
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        $this->joinerId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => 2,
            'name'               => 'Alice Smith',
            'first_name'         => 'Alice',
            'last_name'          => 'Smith',
            'email'              => 'gmj_joiner_test@example.com',
            'password'           => 'x',
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        // Insert a fixture group owned by ownerId.
        $this->groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id'  => 2,
            'owner_id'   => $this->ownerId,
            'name'       => 'Test Join Group',
            'slug'       => 'test-join-group-' . uniqid(),
            'status'     => 'active',
            'created_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Structural checks
    // -----------------------------------------------------------------------

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyGroupMemberJoined::class))
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new NotifyGroupMemberJoined();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -----------------------------------------------------------------------
    // Core happy path
    // -----------------------------------------------------------------------

    public function test_handle_dispatches_notification_to_group_owner(): void
    {
        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->joinerId,
            tenantId: 2,
        );

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                $this->ownerId,
                'global',
                null,
                'group_member_joined',
                Mockery::type('string'),  // content built from joiner name + group name
                '/groups/' . $this->groupId,
                null
            );

        (new NotifyGroupMemberJoined())->handle($event);
    }

    public function test_handle_notification_content_contains_joiner_name(): void
    {
        $capturedContent = null;

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($content) use (&$capturedContent) {
                    $capturedContent = $content;
                    return true;
                }),
                Mockery::any(),
                Mockery::any()
            );

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->joinerId,
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertNotNull($capturedContent);
        $this->assertStringContainsString('Alice', $capturedContent, 'Notification content should contain the joiner\'s first name');
    }

    public function test_handle_notification_link_points_to_group(): void
    {
        $capturedLink = null;

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($link) use (&$capturedLink) {
                    $capturedLink = $link;
                    return true;
                }),
                Mockery::any()
            );

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->joinerId,
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertSame('/groups/' . $this->groupId, $capturedLink);
    }

    // -----------------------------------------------------------------------
    // Owner-joins-own-group guard
    // -----------------------------------------------------------------------

    public function test_handle_skips_notification_when_owner_joins_their_own_group(): void
    {
        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->ownerId,  // owner IS the joiner
            tenantId: 2,
        );

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->never();

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertTrue(true, 'No dispatch should fire when owner joins their own group');
    }

    // -----------------------------------------------------------------------
    // Idempotency guard
    // -----------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery_via_cache_guard(): void
    {
        $handledKey = "notify_group_member_joined:done:2:{$this->groupId}:{$this->joinerId}";
        Cache::put($handledKey, 1, now()->addHour());

        Log::shouldReceive('info')
            ->once()
            ->with(
                'NotifyGroupMemberJoined: duplicate delivery suppressed',
                Mockery::type('array')
            );

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->never();

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->joinerId,
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertTrue(Cache::has($handledKey), 'Done key must still be set after suppressed delivery');
    }

    public function test_handle_sets_done_cache_key_after_successful_dispatch(): void
    {
        $this->dispatcherAlias->shouldReceive('dispatch')->once();

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->joinerId,
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $handledKey = "notify_group_member_joined:done:2:{$this->groupId}:{$this->joinerId}";
        $this->assertTrue(Cache::has($handledKey), 'Done key must be set after successful notification dispatch');
    }

    // -----------------------------------------------------------------------
    // Edge: non-existent group
    // -----------------------------------------------------------------------

    public function test_handle_does_nothing_when_group_does_not_exist(): void
    {
        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->never();

        $event = new GroupMemberJoined(
            groupId: 999999,  // non-existent group
            userId: $this->joinerId,
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertTrue(true, 'handle() must return silently for a non-existent group');
    }

    // -----------------------------------------------------------------------
    // Edge: non-existent joiner
    // -----------------------------------------------------------------------

    public function test_handle_does_nothing_when_joiner_user_does_not_exist(): void
    {
        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->never();

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: 999999,  // non-existent user
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertTrue(true, 'handle() must return silently for a non-existent joiner');
    }

    // -----------------------------------------------------------------------
    // Exception handling
    // -----------------------------------------------------------------------

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->andThrow(new \RuntimeException('Notification service down'));

        Log::shouldReceive('error')
            ->once()
            ->with('NotifyGroupMemberJoined listener failed', Mockery::type('array'));

        // Allow other log calls the listener may emit.
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $this->joinerId,
            tenantId: 2,
        );

        // Must not throw.
        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertTrue(true, 'handle() must swallow exceptions and not rethrow');
    }

    // -----------------------------------------------------------------------
    // Joiner with only 'name' fallback (no first_name/last_name)
    // -----------------------------------------------------------------------

    public function test_handle_uses_name_fallback_when_joiner_has_no_first_name(): void
    {
        // Insert a user with first_name=null, last_name=null, name='MononymousUser'.
        $mononymId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => 2,
            'name'               => 'MononymousUser',
            'first_name'         => null,
            'last_name'          => null,
            'email'              => 'mononymous_gmj_test@example.com',
            'password'           => 'x',
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
        ]);

        $capturedContent = null;

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($content) use (&$capturedContent) {
                    $capturedContent = $content;
                    return true;
                }),
                Mockery::any(),
                Mockery::any()
            );

        $event = new GroupMemberJoined(
            groupId: $this->groupId,
            userId: $mononymId,
            tenantId: 2,
        );

        (new NotifyGroupMemberJoined())->handle($event);

        $this->assertNotNull($capturedContent);
        $this->assertStringContainsString('MononymousUser', $capturedContent, 'Fallback name field should appear in notification content');
    }
}
