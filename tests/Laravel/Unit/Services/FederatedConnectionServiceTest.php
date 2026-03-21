<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederatedConnectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederatedConnectionServiceTest extends TestCase
{
    private FederatedConnectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FederatedConnectionService();
    }

    // =========================================================================
    // sendRequest()
    // =========================================================================

    public function test_sendRequest_rejects_self_connection(): void
    {
        $result = $this->service->sendRequest(1, 1, 2); // same user, same tenant
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('yourself', $result['error']);
    }

    public function test_sendRequest_rejects_already_accepted(): void
    {
        $existing = (object) ['id' => 1, 'status' => 'accepted'];
        DB::shouldReceive('selectOne')->andReturn($existing);

        $result = $this->service->sendRequest(1, 2, 3);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Already connected', $result['error']);
    }

    public function test_sendRequest_rejects_pending_request(): void
    {
        $existing = (object) ['id' => 1, 'status' => 'pending'];
        DB::shouldReceive('selectOne')->andReturn($existing);

        $result = $this->service->sendRequest(1, 2, 3);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already pending', $result['error']);
    }

    public function test_sendRequest_replaces_rejected_request(): void
    {
        $existing = (object) ['id' => 1, 'status' => 'rejected'];
        DB::shouldReceive('selectOne')->andReturn($existing);
        DB::shouldReceive('delete')->once();
        DB::shouldReceive('insert')->once();
        DB::shouldReceive('getPdo->lastInsertId')->andReturn(5);
        Log::shouldReceive('info')->once();

        $result = $this->service->sendRequest(1, 2, 3, 'Hello!');
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['connection_id']);
    }

    // =========================================================================
    // acceptRequest()
    // =========================================================================

    public function test_acceptRequest_fails_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $result = $this->service->acceptRequest(999, 1);
        $this->assertFalse($result['success']);
    }

    public function test_acceptRequest_succeeds(): void
    {
        $connection = (object) ['id' => 1, 'status' => 'pending'];
        DB::shouldReceive('selectOne')->andReturn($connection);
        DB::shouldReceive('update')->once();
        Log::shouldReceive('info')->once();

        $result = $this->service->acceptRequest(1, 5);
        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // rejectRequest()
    // =========================================================================

    public function test_rejectRequest_fails_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $result = $this->service->rejectRequest(999, 1);
        $this->assertFalse($result['success']);
    }

    // =========================================================================
    // removeConnection()
    // =========================================================================

    public function test_removeConnection_fails_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $result = $this->service->removeConnection(999, 1);
        $this->assertFalse($result['success']);
    }

    public function test_removeConnection_succeeds(): void
    {
        $connection = (object) ['id' => 1];
        DB::shouldReceive('selectOne')->andReturn($connection);
        DB::shouldReceive('delete')->once();
        Log::shouldReceive('info')->once();

        $result = $this->service->removeConnection(1, 5);
        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // getStatus()
    // =========================================================================

    public function test_getStatus_returns_none_when_no_connection(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $result = $this->service->getStatus(1, 2, 3);
        $this->assertEquals('none', $result['status']);
        $this->assertNull($result['connection_id']);
    }

    // =========================================================================
    // getConnections()
    // =========================================================================

    public function test_getConnections_defaults_to_accepted_filter(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getConnections(1, 'invalid_status');
        $this->assertEquals([], $result);
    }

    public function test_getConnections_clamps_limit(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getConnections(1, 'accepted', 500);
        $this->assertEquals([], $result);
    }

    // =========================================================================
    // getPendingCount()
    // =========================================================================

    public function test_getPendingCount_returns_integer(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['cnt' => 3]);

        $this->assertEquals(3, $this->service->getPendingCount(1));
    }

    public function test_getPendingCount_returns_zero_on_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals(0, $this->service->getPendingCount(1));
    }
}
