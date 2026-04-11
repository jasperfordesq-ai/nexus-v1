<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Protocols;

use Tests\Laravel\TestCase;
use App\Services\Protocols\NexusAdapter;

class NexusAdapterTest extends TestCase
{
    private NexusAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new NexusAdapter();
    }

    public function test_getProtocolName_returns_nexus(): void
    {
        $this->assertEquals('nexus', NexusAdapter::getProtocolName());
    }

    public function test_getDefaultApiPath_returns_v2_federation(): void
    {
        $this->assertEquals('/api/v2/federation', NexusAdapter::getDefaultApiPath());
    }

    public function test_mapEndpoint_maps_standard_actions(): void
    {
        $this->assertEquals('/members', $this->adapter->mapEndpoint('members'));
        $this->assertEquals('/listings', $this->adapter->mapEndpoint('listings'));
        $this->assertEquals('/transactions', $this->adapter->mapEndpoint('transactions'));
        $this->assertEquals('/health', $this->adapter->mapEndpoint('health'));
        $this->assertEquals('/members/42', $this->adapter->mapEndpoint('member', ['id' => 42]));
        $this->assertEquals('/listings/7', $this->adapter->mapEndpoint('listing', ['id' => 7]));
        $this->assertEquals('/messages', $this->adapter->mapEndpoint('messages'));
        // Unknown actions fall through to /{action}
        $this->assertEquals('/custom', $this->adapter->mapEndpoint('custom'));
    }

    public function test_transformOutboundTransaction_passes_through(): void
    {
        $tx = [
            'amount' => 2.5,
            'sender_user_id' => 10,
            'receiver_user_id' => 20,
            'description' => 'Test transfer',
            'status' => 'completed',
        ];

        $result = $this->adapter->transformOutboundTransaction($tx, 1);

        $this->assertSame($tx, $result);
    }

    public function test_transformInboundMember_adds_source_platform(): void
    {
        $member = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ];

        $result = $this->adapter->transformInboundMember($member);

        $this->assertEquals('nexus', $result['source_platform']);
        $this->assertEquals('Alice', $result['name']);
        $this->assertEquals('alice@example.com', $result['email']);
    }

    public function test_transformInboundMembers_maps_all(): void
    {
        $members = [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
            ['name' => 'Carol'],
        ];

        $result = $this->adapter->transformInboundMembers($members);

        $this->assertCount(3, $result);
        foreach ($result as $member) {
            $this->assertEquals('nexus', $member['source_platform']);
        }
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
        $this->assertEquals('Carol', $result[2]['name']);
    }

    public function test_unwrapResponse_extracts_data_key(): void
    {
        $response = [
            'success' => true,
            'data' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];

        $result = $this->adapter->unwrapResponse($response);

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function test_unwrapResponse_returns_raw_when_no_data_key(): void
    {
        $response = [
            'status' => 'healthy',
            'uptime' => 99.9,
        ];

        $result = $this->adapter->unwrapResponse($response);

        $this->assertSame($response, $result);
    }

    public function test_normalizeWebhookEvent_passes_through(): void
    {
        $this->assertEquals('transaction.completed', $this->adapter->normalizeWebhookEvent('transaction.completed'));
        $this->assertEquals('member.opted_in', $this->adapter->normalizeWebhookEvent('member.opted_in'));
        $this->assertEquals('custom.event', $this->adapter->normalizeWebhookEvent('custom.event'));
    }

    public function test_normalizeWebhookPayload_extracts_event_and_data(): void
    {
        $payload = [
            'event' => 'transaction.completed',
            'data' => [
                'transaction_id' => 'abc-123',
                'amount' => 1.5,
            ],
        ];

        $result = $this->adapter->normalizeWebhookPayload($payload);

        $this->assertEquals('transaction.completed', $result['event']);
        $this->assertEquals('abc-123', $result['data']['transaction_id']);
        $this->assertEquals(1.5, $result['data']['amount']);
    }

    public function test_normalizeWebhookPayload_defaults_to_unknown_event(): void
    {
        $payload = [
            'data' => ['some' => 'value'],
        ];

        $result = $this->adapter->normalizeWebhookPayload($payload);

        $this->assertEquals('unknown', $result['event']);
    }

    public function test_normalizeWebhookPayload_defaults_to_empty_data(): void
    {
        $payload = [
            'event' => 'test.event',
        ];

        $result = $this->adapter->normalizeWebhookPayload($payload);

        $this->assertEquals([], $result['data']);
    }
}
