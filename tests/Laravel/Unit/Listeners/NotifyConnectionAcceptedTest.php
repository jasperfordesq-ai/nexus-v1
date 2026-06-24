<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\ConnectionAccepted;
use App\Listeners\NotifyConnectionAccepted;
use App\Models\Connection;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifyConnectionAccepted listener.
 *
 * Strategy:
 *  - DatabaseTransactions so real user fixtures roll back.
 *  - NotificationDispatcher is alias-mocked BEFORE parent::setUp() (same
 *    pattern as NotifyConnectionRequestTest / NotifyMessageReceivedTest).
 *  - Notification model is alias-mocked the same way for the email-opt-out path.
 *  - Cache is cleared per-test so idempotency keys don't bleed between tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyConnectionAcceptedTest extends TestCase
{
    use DatabaseTransactions;

    /** @var \Mockery\MockInterface */
    private $dispatcherAlias;

    /** @var \Mockery\MockInterface */
    private $notificationAlias;

    protected function setUp(): void
    {
        // Alias mocks must be created BEFORE parent::setUp() / app boot.
        $this->dispatcherAlias = Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldIgnoreMissing();
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class)
            ->shouldIgnoreMissing();

        parent::setUp();

        Queue::fake();
        TenantContext::setById(2);

        // Flush all cache keys so idempotency guards start clean.
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper: insert a minimal user row for tenant 2 and return its id.
    // -------------------------------------------------------------------------
    private function insertUser(string $firstName = 'Test', string $lastName = 'User'): int
    {
        $email = 'test_' . uniqid() . '@example.com';
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => 2,
            'name'       => $firstName . ' ' . $lastName,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'role'       => 'member',
            'status'     => 'active',
            'is_approved' => 1,
            'created_at' => now(),
            'preferred_language' => 'en',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper: build a ConnectionAccepted event with real DB user ids.
    // -------------------------------------------------------------------------
    private function makeEvent(int $requesterId, int $receiverId): ConnectionAccepted
    {
        $requester = new User();
        $requester->id = $requesterId;
        $requester->tenant_id = 2;

        $acceptor = new User();
        $acceptor->id = $receiverId;
        $acceptor->tenant_id = 2;

        $connection = new Connection();
        $connection->id = 9901;
        $connection->requester_id = $requesterId;
        $connection->receiver_id  = $receiverId;
        $connection->tenant_id    = 2;

        return new ConnectionAccepted($connection, $requester, $acceptor, 2);
    }

    // -------------------------------------------------------------------------
    // Test 1: implements ShouldQueue
    // -------------------------------------------------------------------------
    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyConnectionAccepted::class), true)
        );
    }

    // -------------------------------------------------------------------------
    // Test 2: tries and timeout properties exist and are sensible
    // -------------------------------------------------------------------------
    public function test_has_single_try_and_bounded_timeout(): void
    {
        $listener = new NotifyConnectionAccepted();
        $this->assertSame(1, $listener->tries);
        $this->assertGreaterThan(0, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Test 3: Happy path — dispatches notification to requester
    // -------------------------------------------------------------------------
    public function test_handle_dispatches_notification_to_requester(): void
    {
        $requesterId = $this->insertUser('Alice', 'Smith');
        $receiverId  = $this->insertUser('Bob', 'Jones');

        $capturedUid = null;
        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($uid, $scope, $actor, $type, $content, $link, $extra) use (&$capturedUid) {
                $capturedUid = $uid;
                return true;
            });

        $event    = $this->makeEvent($requesterId, $receiverId);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertSame($requesterId, $capturedUid);
    }

    // -------------------------------------------------------------------------
    // Test 4: Notification content contains the acceptor's name
    // -------------------------------------------------------------------------
    public function test_handle_notification_content_contains_acceptor_name(): void
    {
        $requesterId = $this->insertUser('Charlie', 'Brown');
        $receiverId  = $this->insertUser('Diana', 'Prince');

        $capturedContent = null;
        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($uid, $scope, $actor, $type, $content, $link, $extra) use (&$capturedContent) {
                $capturedContent = $content;
                return true;
            });

        $event    = $this->makeEvent($requesterId, $receiverId);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertIsString($capturedContent);
        $this->assertStringContainsString('Diana', $capturedContent);
    }

    // -------------------------------------------------------------------------
    // Test 5: When receiver not found in DB, listener exits without dispatching
    // -------------------------------------------------------------------------
    public function test_handle_does_nothing_when_receiver_not_found(): void
    {
        $requesterId = $this->insertUser('Eve', 'Adams');
        // receiver_id points to a non-existent user
        $nonExistentReceiverId = 99999999;

        $this->dispatcherAlias->shouldReceive('dispatch')->never();

        $requester = new User();
        $requester->id = $requesterId;

        $acceptor = new User();
        $acceptor->id = $nonExistentReceiverId;

        $connection = new Connection();
        $connection->id = 9902;
        $connection->requester_id = $requesterId;
        $connection->receiver_id  = $nonExistentReceiverId;
        $connection->tenant_id    = 2;

        $event    = new ConnectionAccepted($connection, $requester, $acceptor, 2);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 6: Idempotency — second call with same connection_id is a no-op
    // -------------------------------------------------------------------------
    public function test_handle_suppresses_duplicate_delivery_via_cache(): void
    {
        $requesterId = $this->insertUser('Frank', 'Castle');
        $receiverId  = $this->insertUser('Grace', 'Hopper');

        // Seed the "done" cache key that the listener checks
        Cache::put('notify_connection_accepted:done:2:9903', 1, now()->addHour());

        $this->dispatcherAlias->shouldReceive('dispatch')->never();

        $requester = new User();
        $requester->id = $requesterId;
        $acceptor = new User();
        $acceptor->id = $receiverId;
        $connection = new Connection();
        $connection->id = 9903;
        $connection->requester_id = $requesterId;
        $connection->receiver_id  = $receiverId;

        $event    = new ConnectionAccepted($connection, $requester, $acceptor, 2);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 7: After successful handle, the "done" cache key is set
    // -------------------------------------------------------------------------
    public function test_handle_sets_done_cache_key_after_success(): void
    {
        $requesterId = $this->insertUser('Henry', 'Ford');
        $receiverId  = $this->insertUser('Irene', 'Adler');

        $this->dispatcherAlias->shouldReceive('dispatch')->once();

        $requester = new User();
        $requester->id = $requesterId;
        $acceptor = new User();
        $acceptor->id = $receiverId;
        $connection = new Connection();
        $connection->id = 9904;
        $connection->requester_id = $requesterId;
        $connection->receiver_id  = $receiverId;

        $event    = new ConnectionAccepted($connection, $requester, $acceptor, 2);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertTrue(Cache::has('notify_connection_accepted:done:2:9904'));
    }

    // -------------------------------------------------------------------------
    // Test 8: Exception from NotificationDispatcher is caught; error logged; no rethrow
    // -------------------------------------------------------------------------
    public function test_handle_catches_dispatcher_exception_and_logs_error(): void
    {
        $requesterId = $this->insertUser('Jack', 'Sparrow');
        $receiverId  = $this->insertUser('Kate', 'Beckett');

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('SMTP timeout'));

        Log::shouldReceive('error')
            ->once()
            ->with('NotifyConnectionAccepted listener failed', Mockery::type('array'));

        $event    = $this->makeEvent($requesterId, $receiverId);
        $listener = new NotifyConnectionAccepted();

        // Must not throw
        $listener->handle($event);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Test 9: Email opt-out path — uses Notification::createNotification instead
    // -------------------------------------------------------------------------
    public function test_handle_uses_bell_only_when_email_notifications_disabled(): void
    {
        $requesterId = $this->insertUser('Liam', 'Neeson');
        $receiverId  = $this->insertUser('Maya', 'Angelou');

        // Set notification preferences: email_connections = false
        DB::table('users')
            ->where('id', $requesterId)
            ->update([
                'notification_preferences' => json_encode(['email_connections' => false]),
            ]);

        // Should NOT call the full NotificationDispatcher::dispatch
        $this->dispatcherAlias->shouldReceive('dispatch')->never();

        // Should call Notification::createNotification (bell-only path)
        $capturedNotifUid = null;
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->withArgs(function (int $uid, string $msg, string $link, string $type) use (&$capturedNotifUid) {
                $capturedNotifUid = $uid;
                return true;
            });

        // fanOutPush is also called on the opt-out path
        $this->dispatcherAlias
            ->shouldReceive('fanOutPush')
            ->once();

        $event    = $this->makeEvent($requesterId, $receiverId);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertSame($requesterId, $capturedNotifUid);
    }

    // -------------------------------------------------------------------------
    // Test 10: Claim key is released from cache even when handler throws
    // -------------------------------------------------------------------------
    public function test_handle_releases_claim_key_after_exception(): void
    {
        $requesterId = $this->insertUser('Noah', 'Webster');
        $receiverId  = $this->insertUser('Olivia', 'Wilde');

        $this->dispatcherAlias
            ->shouldReceive('dispatch')
            ->andThrow(new \RuntimeException('Boom'));

        Log::shouldReceive('error')->once();

        $requester = new User();
        $requester->id = $requesterId;
        $acceptor = new User();
        $acceptor->id = $receiverId;
        $connection = new Connection();
        $connection->id = 9905;
        $connection->requester_id = $requesterId;
        $connection->receiver_id  = $receiverId;

        $event    = new ConnectionAccepted($connection, $requester, $acceptor, 2);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        // The claim key must have been released (forget in finally block)
        $claimKey = 'notify_connection_accepted:claim:2:9905';
        $this->assertFalse(Cache::has($claimKey), 'Claim key should be released after exception');
    }

    // -------------------------------------------------------------------------
    // Test 11: Concurrent claim suppresses second delivery
    // -------------------------------------------------------------------------
    public function test_handle_suppresses_concurrent_delivery_via_claim_key(): void
    {
        $requesterId = $this->insertUser('Peter', 'Parker');
        $receiverId  = $this->insertUser('Quinn', 'Fabray');

        // Pre-seed the claim key to simulate another worker running simultaneously
        Cache::add('notify_connection_accepted:claim:2:9906', 1, now()->addMinutes(5));

        $this->dispatcherAlias->shouldReceive('dispatch')->never();

        $requester = new User();
        $requester->id = $requesterId;
        $acceptor = new User();
        $acceptor->id = $receiverId;
        $connection = new Connection();
        $connection->id = 9906;
        $connection->requester_id = $requesterId;
        $connection->receiver_id  = $receiverId;

        $event    = new ConnectionAccepted($connection, $requester, $acceptor, 2);
        $listener = new NotifyConnectionAccepted();
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
