<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Protocols;

use App\Contracts\FederationProtocolAdapter;
use App\Services\Protocols\CreditCommonsAdapter;
use App\Services\Protocols\KomunitinAdapter;
use App\Services\Protocols\NexusAdapter;
use App\Services\TimeOverflowAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Verifies all four protocol adapters implement the expanded outbound interface
 * consistently — 7 new entities (listing, review, event, group, connection,
 * volunteering, member) must each have a transform method and mapEndpoint case.
 */
class AdapterCoverageTest extends TestCase
{
    /** @return list<array{0: FederationProtocolAdapter, 1: string}> */
    public static function adapters(): array
    {
        return [
            [new NexusAdapter(), 'nexus'],
            [new KomunitinAdapter(), 'komunitin'],
            [new CreditCommonsAdapter(), 'credit_commons'],
            [new TimeOverflowAdapter(), 'timeoverflow'],
        ];
    }

    private const NEW_ENTITIES = [
        'listings', 'reviews', 'events', 'groups', 'connections', 'volunteering', 'members',
    ];

    private const TRANSFORM_METHODS = [
        'transformOutboundListing',
        'transformOutboundReview',
        'transformOutboundEvent',
        'transformOutboundGroup',
        'transformOutboundConnection',
        'transformOutboundVolunteering',
        'transformOutboundMember',
    ];

    private function sampleFor(string $method): array
    {
        return match ($method) {
            'transformOutboundListing'      => ['id' => 1, 'title' => 'A', 'description' => 'B', 'type' => 'offer', 'user_id' => 42],
            'transformOutboundReview'       => ['id' => 1, 'rating' => 5, 'comment' => 'Great', 'reviewer_id' => 7, 'receiver_external_id' => 'r-1', 'created_at' => '2026-04-12T00:00:00Z'],
            'transformOutboundEvent'        => ['id' => 1, 'title' => 'Evt', 'description' => 'D', 'starts_at' => '2026-05-01T10:00:00Z'],
            'transformOutboundGroup'        => ['id' => 1, 'name' => 'G', 'description' => 'D', 'privacy' => 'public'],
            'transformOutboundConnection'   => ['id' => 1, 'requester_id' => 1, 'recipient_id' => 2, 'status' => 'pending'],
            'transformOutboundVolunteering' => ['id' => 1, 'title' => 'V', 'description' => 'D', 'organization' => 'O'],
            'transformOutboundMember'       => ['id' => 1, 'name' => 'N', 'email' => 'a@b.c', 'balance' => 3.0],
            default                         => [],
        };
    }

    /**
     * @dataProvider adapters
     */
    public function test_adapter_implements_interface(FederationProtocolAdapter $adapter, string $name): void
    {
        $this->assertInstanceOf(FederationProtocolAdapter::class, $adapter, $name);
    }

    /**
     * @dataProvider adapters
     */
    public function test_new_transform_methods_return_non_empty_array(FederationProtocolAdapter $adapter, string $name): void
    {
        foreach (self::TRANSFORM_METHODS as $method) {
            $this->assertTrue(method_exists($adapter, $method), "{$name} missing {$method}");
            $result = $adapter->{$method}($this->sampleFor($method));
            $this->assertIsArray($result, "{$name}::{$method} must return array");
            $this->assertNotEmpty($result, "{$name}::{$method} returned an empty array");
        }
    }

    /**
     * @dataProvider adapters
     */
    public function test_map_endpoint_defined_for_new_entities(FederationProtocolAdapter $adapter, string $name): void
    {
        foreach (self::NEW_ENTITIES as $entity) {
            $endpoint = $adapter->mapEndpoint($entity);
            $this->assertIsString($endpoint, "{$name}::mapEndpoint({$entity}) must return string");
            $this->assertNotSame('', $endpoint, "{$name}::mapEndpoint({$entity}) returned empty");
            $this->assertStringStartsWith('/', $endpoint, "{$name}::mapEndpoint({$entity}) must be a path");
        }
    }

    // ----------------------------------------------------------------
    // Komunitin envelope checks — JSON:API shape
    // ----------------------------------------------------------------

    public function test_komunitin_envelopes_are_jsonapi_shaped(): void
    {
        $adapter = new KomunitinAdapter();

        foreach (self::TRANSFORM_METHODS as $method) {
            $envelope = $adapter->{$method}($this->sampleFor($method));
            $this->assertArrayHasKey('data', $envelope, "Komunitin::{$method} missing 'data' key");
            $this->assertArrayHasKey('type', $envelope['data'], "Komunitin::{$method} missing 'data.type'");
            $this->assertArrayHasKey('attributes', $envelope['data'], "Komunitin::{$method} missing 'data.attributes'");
            $this->assertIsArray($envelope['data']['attributes']);
        }
    }

    // ----------------------------------------------------------------
    // CC / TO graceful extension envelope — for unsupported entities
    // ----------------------------------------------------------------

    /**
     * CC doesn't natively model events/groups/connections/volunteering/reviews.
     * These must return a wrapped extension envelope (not throw).
     */
    public function test_credit_commons_extension_fallback_shape(): void
    {
        $adapter = new CreditCommonsAdapter();
        $unsupported = ['transformOutboundEvent', 'transformOutboundGroup', 'transformOutboundVolunteering', 'transformOutboundReview', 'transformOutboundConnection'];

        foreach ($unsupported as $method) {
            $result = $adapter->{$method}($this->sampleFor($method));
            $this->assertArrayHasKey('type', $result, "CC::{$method} missing 'type'");
            $this->assertStringStartsWith('nexus_extension_', $result['type'], "CC::{$method} extension type mismatch");
            $this->assertArrayHasKey('payload', $result, "CC::{$method} missing 'payload'");
            $this->assertIsArray($result['payload']);
        }
    }

    public function test_timeoverflow_extension_fallback_for_unsupported(): void
    {
        $adapter = new TimeOverflowAdapter();
        // TO has native posts for listings and members, but not events/groups/etc.
        $unsupported = ['transformOutboundEvent', 'transformOutboundGroup', 'transformOutboundVolunteering', 'transformOutboundReview', 'transformOutboundConnection'];

        foreach ($unsupported as $method) {
            $result = $adapter->{$method}($this->sampleFor($method));
            $this->assertArrayHasKey('type', $result, "TO::{$method} missing 'type'");
            $this->assertStringStartsWith('nexus_extension_', $result['type'], "TO::{$method} extension type mismatch");
            $this->assertArrayHasKey('payload', $result, "TO::{$method} missing 'payload'");
        }
    }

    public function test_timeoverflow_native_listing_is_post_wrapped(): void
    {
        $adapter = new TimeOverflowAdapter();
        $result = $adapter->transformOutboundListing($this->sampleFor('transformOutboundListing'));
        $this->assertArrayHasKey('post', $result);
        $this->assertIsArray($result['post']);
        $this->assertArrayHasKey('title', $result['post']);
    }

    public function test_timeoverflow_native_member_is_member_wrapped(): void
    {
        $adapter = new TimeOverflowAdapter();
        $result = $adapter->transformOutboundMember($this->sampleFor('transformOutboundMember'));
        $this->assertArrayHasKey('member', $result);
        $this->assertIsArray($result['member']);
    }

    public function test_nexus_adapter_passthrough_preserves_input(): void
    {
        $adapter = new NexusAdapter();
        $input = ['foo' => 'bar', 'nested' => ['a' => 1]];
        foreach (self::TRANSFORM_METHODS as $method) {
            $this->assertSame($input, $adapter->{$method}($input), "Nexus::{$method} should pass through");
        }
    }
}
