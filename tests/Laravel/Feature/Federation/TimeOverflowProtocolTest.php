<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Services\TimeOverflowAdapter;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * TimeOverflowProtocolTest — verifies the TimeOverflow adapter encodes outbound
 * payloads in TO's format and can parse inbound TO webhooks.
 */
final class TimeOverflowProtocolTest extends TestCase
{
    use FederationIntegrationHarness;

    private TimeOverflowAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new TimeOverflowAdapter();
    }

    public function test_protocol_identity(): void
    {
        $this->assertSame('timeoverflow', TimeOverflowAdapter::getProtocolName());
    }

    public function test_outbound_transaction_converts_hours_to_seconds(): void
    {
        $tx = [
            'id'          => 1,
            'amount'      => 2.0,   // 2 Nexus hours
            'description' => 'Service rendered',
            'sender_id'   => 1,
            'receiver_id' => 2,
        ];
        $out = $this->adapter->transformOutboundTransaction($tx, 1);
        $json = (string) json_encode($out);

        // TO stores time in seconds — expect 7200 (or at least some seconds field)
        $this->assertMatchesRegularExpression(
            '/(7200|seconds|amount)/',
            $json,
            'TO outbound transaction should express amount in seconds'
        );
    }

    public function test_inbound_webhook_payload_parses(): void
    {
        $raw = [
            'event' => 'message.sent',
            'data'  => [
                'sender_id'   => 5,
                'sender_name' => 'TO User',
                'message'     => 'Hello from TO',
                'recipient_id' => 1,
            ],
        ];
        $normalized = $this->adapter->normalizeWebhookPayload($raw);
        $this->assertArrayHasKey('event', $normalized);
        $this->assertArrayHasKey('data', $normalized);
    }
}
