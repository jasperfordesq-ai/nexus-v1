<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederatedMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederatedMessageServiceTest extends TestCase
{
    // =========================================================================
    // sendMessage()
    // =========================================================================

    public function test_sendMessage_fails_when_sender_not_found(): void
    {
        DB::shouldReceive('table->where->first')->andReturn(null);

        $result = FederatedMessageService::sendMessage(999, 1, 2, 'Hi', 'Body');
        $this->assertFalse($result['success']);
        $this->assertEquals('Sender not found', $result['error']);
    }

    public function test_sendMessage_fails_when_receiver_not_found(): void
    {
        $sender = (object) ['id' => 1, 'tenant_id' => 2];
        DB::shouldReceive('table->where->first')->andReturn($sender);
        DB::shouldReceive('table->where->where->first')->andReturn(null);

        $result = FederatedMessageService::sendMessage(1, 999, 3, 'Hi', 'Body');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Receiver not found', $result['error']);
    }

    public function test_sendMessage_fails_when_sender_not_opted_in(): void
    {
        $sender = (object) ['id' => 1, 'tenant_id' => 2];
        $receiver = (object) ['id' => 2, 'tenant_id' => 3];
        $settings = (object) ['federation_optin' => false, 'messaging_enabled_federated' => false];

        DB::shouldReceive('table->where->first')->andReturn($sender, $settings);
        DB::shouldReceive('table->where->where->first')->andReturn($receiver);

        $result = FederatedMessageService::sendMessage(1, 2, 3, 'Hi', 'Body');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('federated messaging', $result['error']);
    }

    // =========================================================================
    // getUnreadCount()
    // =========================================================================

    public function test_getUnreadCount_returns_integer(): void
    {
        DB::shouldReceive('table->where->where->count')->andReturn(7);

        $this->assertEquals(7, FederatedMessageService::getUnreadCount(1));
    }

    public function test_getUnreadCount_returns_zero_on_error(): void
    {
        DB::shouldReceive('table->where->where->count')->andThrow(new \Exception('error'));

        $this->assertEquals(0, FederatedMessageService::getUnreadCount(1));
    }

    // =========================================================================
    // storeExternalMessage()
    // =========================================================================

    public function test_storeExternalMessage_fails_when_receiver_not_found(): void
    {
        DB::shouldReceive('table->where->first')->andReturn(null);

        $result = FederatedMessageService::storeExternalMessage(999, 1, 2, 'Sender', 'Partner', 'Subject', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_storeExternalMessage_succeeds(): void
    {
        $receiver = (object) ['id' => 1, 'tenant_id' => 2];
        DB::shouldReceive('table->where->first')->andReturn($receiver);
        DB::shouldReceive('table->insertGetId')->andReturn(10);

        $result = FederatedMessageService::storeExternalMessage(1, 5, 100, 'Sender Name', 'Partner Name', 'Subject', 'Body');
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['message_id']);
    }
}
