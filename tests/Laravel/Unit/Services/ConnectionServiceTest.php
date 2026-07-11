<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\Connection;
use App\Models\User;
use App\Services\ConnectionService;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for ConnectionService (member-to-member connections / friend graph).
 *
 * Previously eight of ten methods were markTestIncomplete ("Eloquent models
 * cannot use shouldReceive()"). They are now real assertions against the
 * nexus_test MariaDB — the connection graph gates messaging/visibility and
 * must be guarded, not stubbed.
 *
 * Gotchas honoured:
 *  - use DatabaseTransactions so every row rolls back.
 *  - Re-pin TenantContext immediately before each tenant-scoped service call;
 *    User::factory()->create() drifts TenantContext and the service reads
 *    TenantContext::getId() through the HasTenantScope global scope.
 *  - Real users.id FKs (requester_id / receiver_id reference users); a literal
 *    like 1 would let request() fail its same-tenant check and never insert.
 *  - request() validates both users share a tenant via a raw DB::table('users')
 *    lookup, then dispatches ConnectionRequested INSIDE a try/catch, so a
 *    notification/broadcast failure can never break the row creation under test.
 */
class ConnectionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')->zeroOrMoreTimes();
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);
    }

    /**
     * Create a user in the test tenant and re-pin the tenant context, since
     * factory creation drifts TenantContext.
     */
    private function makeUser(array $attributes = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create($attributes);
        TenantContext::setById($this->testTenantId);

        return $user;
    }

    // --- Pure validation guards (return/throw before any DB access) ---

    public function test_request_throws_when_connecting_with_self(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You cannot connect with yourself');

        ConnectionService::request(1, 1);
    }

    public function test_sendRequest_returns_false_when_same_user(): void
    {
        $result = ConnectionService::sendRequest(1, 1);
        $this->assertFalse($result);
    }

    // --- Real-DB behaviour (converted from markTestIncomplete) ---

    public function test_request_throws_when_connection_already_exists(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        // First request succeeds.
        TenantContext::setById($this->testTenantId);
        $first = ConnectionService::request((int) $a->id, (int) $b->id);
        $this->assertInstanceOf(Connection::class, $first);

        // A second request in EITHER direction must be rejected.
        TenantContext::setById($this->testTenantId);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A connection with this user already exists');
        ConnectionService::request((int) $b->id, (int) $a->id);
    }

    public function test_request_creates_pending_row_and_returns_connection(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertSame('pending', $connection->status);
        $this->assertSame((int) $requester->id, (int) $connection->requester_id);
        $this->assertSame((int) $receiver->id, (int) $connection->receiver_id);

        // Exactly one row written, scoped to the test tenant.
        $this->assertSame(1, (int) DB::table('connections')
            ->where('tenant_id', $this->testTenantId)
            ->where('requester_id', $requester->id)
            ->where('receiver_id', $receiver->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_sendRequest_returns_true_on_success(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $result = ConnectionService::sendRequest((int) $requester->id, (int) $receiver->id);

        $this->assertTrue($result);
        $this->assertSame(1, (int) DB::table('connections')
            ->where('tenant_id', $this->testTenantId)
            ->where('requester_id', $requester->id)
            ->where('receiver_id', $receiver->id)
            ->count());
    }

    public function test_accept_transitions_pending_to_accepted(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        TenantContext::setById($this->testTenantId);
        $accepted = ConnectionService::accept((int) $connection->id, (int) $receiver->id);

        $this->assertSame('accepted', $accepted->status);
        $this->assertSame('accepted', (string) DB::table('connections')
            ->where('id', $connection->id)
            ->value('status'));
    }

    public function test_accept_rechecks_policy_and_leaves_request_pending_when_denied(): void
    {
        $requester = $this->makeUser();
        $receiver = $this->makeUser();

        $connectionId = DB::table('connections')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'requester_id' => $requester->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with(
                (int) $requester->id,
                (int) $receiver->id,
                $this->testTenantId,
                'connection_accept',
            )
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);
        TenantContext::setById($this->testTenantId);

        try {
            ConnectionService::accept((int) $connectionId, (int) $receiver->id);
            $this->fail('Expected safeguarding denial');
        } catch (SafeguardingPolicyException $e) {
            $this->assertSame('VETTING_REQUIRED', $e->reasonCode);
        }

        $this->assertSame('pending', (string) DB::table('connections')
            ->where('id', $connectionId)
            ->value('status'));
    }

    public function test_acceptRequest_returns_false_when_not_receiver(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        // Only the receiver may accept; the requester accepting must fail and
        // leave the status pending.
        TenantContext::setById($this->testTenantId);
        $this->assertFalse(ConnectionService::acceptRequest((int) $connection->id, (int) $requester->id));
        $this->assertSame('pending', (string) DB::table('connections')
            ->where('id', $connection->id)
            ->value('status'));
    }

    public function test_destroy_returns_false_when_not_participant(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();
        $outsider  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        // A non-participant cannot delete the connection.
        TenantContext::setById($this->testTenantId);
        $this->assertFalse(ConnectionService::destroy((int) $connection->id, (int) $outsider->id));
        // Row must still exist.
        $this->assertSame(1, (int) DB::table('connections')->where('id', $connection->id)->count());
    }

    public function test_destroy_removes_row_for_participant(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        TenantContext::setById($this->testTenantId);
        $this->assertTrue(ConnectionService::destroy((int) $connection->id, (int) $requester->id));
        $this->assertSame(0, (int) DB::table('connections')->where('id', $connection->id)->count());
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $user = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $this->assertNull(ConnectionService::getById(99999999, (int) $user->id));
    }

    public function test_getById_returns_array_for_participant(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        TenantContext::setById($this->testTenantId);
        $result = ConnectionService::getById((int) $connection->id, (int) $requester->id);

        $this->assertIsArray($result);
        $this->assertSame((int) $connection->id, (int) $result['id']);
        $this->assertSame((int) $requester->id, (int) $result['requester_id']);
        $this->assertSame((int) $receiver->id, (int) $result['receiver_id']);
    }

    public function test_getStatus_returns_none_when_no_connection(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $status = ConnectionService::getStatus((int) $a->id, (int) $b->id);

        $this->assertSame('none', $status['status']);
        $this->assertNull($status['connection_id']);
        $this->assertNull($status['direction']);
    }

    public function test_getStatus_returns_connected_for_accepted(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);
        TenantContext::setById($this->testTenantId);
        ConnectionService::accept((int) $connection->id, (int) $receiver->id);

        TenantContext::setById($this->testTenantId);
        $status = ConnectionService::getStatus((int) $requester->id, (int) $receiver->id);

        $this->assertSame('connected', $status['status']);
        $this->assertSame((int) $connection->id, (int) $status['connection_id']);
        $this->assertNull($status['direction']);
    }

    public function test_getStatus_returns_pending_sent_direction(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        // From the requester's perspective the pending request was SENT.
        TenantContext::setById($this->testTenantId);
        $sent = ConnectionService::getStatus((int) $requester->id, (int) $receiver->id);
        $this->assertSame('pending_sent', $sent['status']);
        $this->assertSame('sent', $sent['direction']);
        $this->assertSame((int) $connection->id, (int) $sent['connection_id']);

        // From the receiver's perspective the same request was RECEIVED.
        TenantContext::setById($this->testTenantId);
        $received = ConnectionService::getStatus((int) $receiver->id, (int) $requester->id);
        $this->assertSame('pending_received', $received['status']);
        $this->assertSame('received', $received['direction']);
    }

    public function test_getPendingCounts_counts_received_sent_and_friends(): void
    {
        $me = $this->makeUser();
        $sender = $this->makeUser();
        $target = $this->makeUser();
        $friend = $this->makeUser();

        // sender -> me  (a request RECEIVED by me, still pending)
        TenantContext::setById($this->testTenantId);
        ConnectionService::request((int) $sender->id, (int) $me->id);

        // me -> target  (a request SENT by me, still pending)
        TenantContext::setById($this->testTenantId);
        ConnectionService::request((int) $me->id, (int) $target->id);

        // me <-> friend, accepted (a friend)
        TenantContext::setById($this->testTenantId);
        $friendConn = ConnectionService::request((int) $me->id, (int) $friend->id);
        TenantContext::setById($this->testTenantId);
        ConnectionService::accept((int) $friendConn->id, (int) $friend->id);

        TenantContext::setById($this->testTenantId);
        $counts = ConnectionService::getPendingCounts((int) $me->id);

        $this->assertSame(1, (int) $counts['received']);
        $this->assertSame(1, (int) $counts['sent']);
        $this->assertSame(1, (int) $counts['total_friends']);
    }

    public function test_rejectRequest_returns_false_when_not_found(): void
    {
        $user = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $this->assertFalse(ConnectionService::rejectRequest(99999999, (int) $user->id));
    }

    public function test_rejectRequest_deletes_pending_request_for_receiver(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        // The requester cannot reject (only the receiver can).
        TenantContext::setById($this->testTenantId);
        $this->assertFalse(ConnectionService::rejectRequest((int) $connection->id, (int) $requester->id));
        $this->assertSame(1, (int) DB::table('connections')->where('id', $connection->id)->count());

        // The receiver rejecting deletes the row.
        TenantContext::setById($this->testTenantId);
        $this->assertTrue(ConnectionService::rejectRequest((int) $connection->id, (int) $receiver->id));
        $this->assertSame(0, (int) DB::table('connections')->where('id', $connection->id)->count());
    }

    public function test_delete_delegates_to_destroy(): void
    {
        $requester = $this->makeUser();
        $receiver  = $this->makeUser();

        TenantContext::setById($this->testTenantId);
        $connection = ConnectionService::request((int) $requester->id, (int) $receiver->id);

        // delete() is a thin alias for destroy(): participant succeeds, removes row.
        TenantContext::setById($this->testTenantId);
        $this->assertTrue(ConnectionService::delete((int) $connection->id, (int) $receiver->id));
        $this->assertSame(0, (int) DB::table('connections')->where('id', $connection->id)->count());
    }
}
