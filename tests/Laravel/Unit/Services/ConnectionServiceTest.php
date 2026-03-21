<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ConnectionService;
use App\Models\Connection;
use Mockery;

class ConnectionServiceTest extends TestCase
{
    public function test_request_throws_when_connecting_with_self(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect with yourself');

        ConnectionService::request(1, 1);
    }

    public function test_request_throws_when_connection_already_exists(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_sendRequest_returns_false_when_same_user(): void
    {
        $result = ConnectionService::sendRequest(1, 1);
        $this->assertFalse($result);
    }

    public function test_destroy_returns_false_when_not_participant(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getStatus_returns_none_when_no_connection(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getStatus_returns_connected_for_accepted(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getStatus_returns_pending_sent_direction(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_rejectRequest_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_delegates_to_destroy(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
