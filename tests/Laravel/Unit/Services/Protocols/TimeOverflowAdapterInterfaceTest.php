<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Protocols;

use Tests\Laravel\TestCase;
use App\Contracts\FederationProtocolAdapter;
use App\Services\Protocols\TimeOverflowAdapter;

/**
 * Tests that TimeOverflowAdapter correctly implements the FederationProtocolAdapter
 * interface and maps TimeOverflow-specific terminology to Nexus concepts.
 *
 * TimeOverflow uses different names for entities:
 *   - "posts" instead of "listings"
 *   - "transfers" instead of "transactions"
 *   - Time stored in seconds (Nexus uses hours)
 */
class TimeOverflowAdapterInterfaceTest extends TestCase
{
    private TimeOverflowAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new TimeOverflowAdapter();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Interface & identity
    // ──────────────────────────────────────────────────────────────────────

    public function test_implements_federation_protocol_adapter_interface(): void
    {
        $this->assertInstanceOf(FederationProtocolAdapter::class, $this->adapter);
    }

    public function test_getProtocolName_returns_timeoverflow(): void
    {
        $this->assertSame('timeoverflow', TimeOverflowAdapter::getProtocolName());
    }

    public function test_getDefaultApiPath_returns_api_v1(): void
    {
        $this->assertSame('/api/v1', TimeOverflowAdapter::getDefaultApiPath());
    }

    // ──────────────────────────────────────────────────────────────────────
    // Endpoint mapping
    // ──────────────────────────────────────────────────────────────────────

    public function test_mapEndpoint_maps_listings_to_posts(): void
    {
        // TimeOverflow calls listings "posts"
        $this->assertSame('/posts', $this->adapter->mapEndpoint('listings'));
    }

    public function test_mapEndpoint_maps_transactions_to_transfers(): void
    {
        // TimeOverflow calls transactions "transfers"
        $this->assertSame('/transfers', $this->adapter->mapEndpoint('transactions'));
    }

    public function test_mapEndpoint_maps_members(): void
    {
        $this->assertSame('/members', $this->adapter->mapEndpoint('members'));
    }

    public function test_mapEndpoint_maps_single_member_with_id(): void
    {
        $this->assertSame('/members/42', $this->adapter->mapEndpoint('member', ['id' => 42]));
    }

    public function test_mapEndpoint_maps_single_listing_with_id(): void
    {
        $this->assertSame('/posts/99', $this->adapter->mapEndpoint('listing', ['id' => 99]));
    }

    public function test_mapEndpoint_maps_health(): void
    {
        $this->assertSame('/health', $this->adapter->mapEndpoint('health'));
    }

    public function test_mapEndpoint_falls_back_to_action_name(): void
    {
        // Unknown actions use the action name as the path
        $this->assertSame('/custom-action', $this->adapter->mapEndpoint('custom-action'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Outbound transformations
    // ──────────────────────────────────────────────────────────────────────

    public function test_transformOutboundTransaction_delegates_to_buildTransferPayload(): void
    {
        $nexusTransaction = [
            'id' => 123,
            'direction' => 'outbound',
            'remote_account_id' => 456,
            'sender_email' => 'alice@example.com',
            'amount' => 2.5,
            'description' => 'Gardening help',
        ];

        $result = $this->adapter->transformOutboundTransaction($nexusTransaction, 10);

        $this->assertSame(10, $result['partner_id']);
        $this->assertSame('123', $result['external_transaction_id']);
        $this->assertSame('outbound', $result['direction']);
        $this->assertSame(456, $result['local_account_id']);
        $this->assertSame('alice@example.com', $result['remote_user_identifier']);
        // 2.5 hours = 9000 seconds
        $this->assertSame(9000, $result['amount']);
        $this->assertSame('Gardening help', $result['reason']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Inbound transformations
    // ──────────────────────────────────────────────────────────────────────

    public function test_transformInboundMember_delegates_to_transformMember(): void
    {
        $toMember = [
            'id' => 7,
            'username' => 'bob',
            'email' => 'bob@example.com',
            'balance' => 7200, // 2 hours in seconds
            'description' => 'Loves cooking',
        ];

        $result = $this->adapter->transformInboundMember($toMember);

        $this->assertSame(7, $result['external_id']);
        $this->assertSame('bob', $result['name']);
        $this->assertSame('bob@example.com', $result['email']);
        $this->assertSame(2.0, $result['balance']); // 7200s -> 2h
        $this->assertSame('Loves cooking', $result['bio']);
        $this->assertSame('timeoverflow', $result['source_platform']);
    }

    public function test_transformInboundListing_delegates_to_transformListing(): void
    {
        $toListing = [
            'id' => 55,
            'title' => 'Dog Walking',
            'description' => 'Walking your dog in the park',
            'type' => 'offer',
            'category' => 'Pets',
            'tags' => ['dogs', 'outdoors'],
        ];

        $result = $this->adapter->transformInboundListing($toListing);

        $this->assertSame(55, $result['external_id']);
        $this->assertSame('Dog Walking', $result['title']);
        $this->assertSame('offer', $result['type']);
        $this->assertSame('Pets', $result['category_name']);
        $this->assertSame(['dogs', 'outdoors'], $result['tags']);
        $this->assertSame('timeoverflow', $result['source_platform']);
    }

    public function test_transformInboundListing_maps_inquiry_to_request(): void
    {
        $toListing = [
            'id' => 56,
            'title' => 'Need Guitar Lessons',
            'type' => 'inquiry', // TO uses "inquiry", Nexus uses "request"
        ];

        $result = $this->adapter->transformInboundListing($toListing);

        $this->assertSame('request', $result['type']);
    }

    public function test_transformInboundTransaction_delegates_to_transformTransactionResponse(): void
    {
        $toResponse = [
            'data' => [
                'federation_transaction_id' => 'abc-123',
                'local_transfer_id' => 'def-456',
                'status' => 'completed',
                'amount' => 3600, // 1 hour in seconds
                'direction' => 'inbound',
                'completed_at' => '2026-04-01T12:00:00Z',
            ],
        ];

        $result = $this->adapter->transformInboundTransaction($toResponse);

        $this->assertSame('abc-123', $result['external_transaction_id']);
        $this->assertSame('def-456', $result['external_transfer_id']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1.0, $result['amount_hours']); // 3600s -> 1h
        $this->assertSame('inbound', $result['direction']);
        $this->assertSame('timeoverflow', $result['source_platform']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Webhook normalization
    // ──────────────────────────────────────────────────────────────────────

    public function test_normalizeWebhookEvent_passes_through(): void
    {
        // TimeOverflow uses the same event names as Nexus — passthrough
        $this->assertSame('transaction.completed', $this->adapter->normalizeWebhookEvent('transaction.completed'));
        $this->assertSame('member.created', $this->adapter->normalizeWebhookEvent('member.created'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Response unwrapping
    // ──────────────────────────────────────────────────────────────────────

    public function test_unwrapResponse_extracts_data_key(): void
    {
        $response = [
            'data' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];

        $result = $this->adapter->unwrapResponse($response);

        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }

    public function test_unwrapResponse_returns_response_when_no_data_key(): void
    {
        $response = ['id' => 1, 'name' => 'Alice'];

        $result = $this->adapter->unwrapResponse($response);

        $this->assertSame($response, $result);
    }
}
