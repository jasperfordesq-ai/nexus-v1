<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Contracts\FederationProtocolAdapter;
use App\Services\FederationExternalApiClient;
use App\Services\Protocols\NexusAdapter;
use App\Services\Protocols\KomunitinAdapter;
use App\Services\Protocols\CreditCommonsAdapter;
use App\Services\TimeOverflowAdapter;

/**
 * Tests for FederationExternalApiClient adapter resolution and protocol registry.
 *
 * Verifies that the correct protocol adapter is instantiated based on the
 * protocol_type string, that all four protocols are registered, and that
 * resolveAdapter works with both partner arrays and partner IDs (via fallback).
 */
class FederationExternalApiClientAdapterTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────
    // createAdapter() tests
    // ──────────────────────────────────────────────────────────────────────

    public function test_createAdapter_returns_NexusAdapter_for_nexus(): void
    {
        $adapter = FederationExternalApiClient::createAdapter('nexus');

        $this->assertInstanceOf(NexusAdapter::class, $adapter);
        $this->assertInstanceOf(FederationProtocolAdapter::class, $adapter);
    }

    public function test_createAdapter_returns_TimeOverflowAdapter_for_timeoverflow(): void
    {
        $adapter = FederationExternalApiClient::createAdapter('timeoverflow');

        $this->assertInstanceOf(TimeOverflowAdapter::class, $adapter);
        $this->assertInstanceOf(FederationProtocolAdapter::class, $adapter);
    }

    public function test_createAdapter_returns_KomunitinAdapter_for_komunitin(): void
    {
        $adapter = FederationExternalApiClient::createAdapter('komunitin');

        $this->assertInstanceOf(KomunitinAdapter::class, $adapter);
        $this->assertInstanceOf(FederationProtocolAdapter::class, $adapter);
    }

    public function test_createAdapter_returns_CreditCommonsAdapter_for_credit_commons(): void
    {
        $adapter = FederationExternalApiClient::createAdapter('credit_commons');

        $this->assertInstanceOf(CreditCommonsAdapter::class, $adapter);
        $this->assertInstanceOf(FederationProtocolAdapter::class, $adapter);
    }

    public function test_createAdapter_defaults_to_NexusAdapter_for_unknown(): void
    {
        $adapter = FederationExternalApiClient::createAdapter('some_unknown_protocol');

        $this->assertInstanceOf(NexusAdapter::class, $adapter);
    }

    // ──────────────────────────────────────────────────────────────────────
    // getSupportedProtocols() tests
    // ──────────────────────────────────────────────────────────────────────

    public function test_getSupportedProtocols_returns_all_four(): void
    {
        $protocols = FederationExternalApiClient::getSupportedProtocols();

        $this->assertIsArray($protocols);
        $this->assertCount(4, $protocols);
        $this->assertArrayHasKey('nexus', $protocols);
        $this->assertArrayHasKey('timeoverflow', $protocols);
        $this->assertArrayHasKey('komunitin', $protocols);
        $this->assertArrayHasKey('credit_commons', $protocols);

        // Verify display names are non-empty strings
        foreach ($protocols as $key => $displayName) {
            $this->assertIsString($displayName, "Display name for '{$key}' should be a string");
            $this->assertNotEmpty($displayName, "Display name for '{$key}' should not be empty");
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // resolveAdapter() with partner array tests
    // ──────────────────────────────────────────────────────────────────────

    public function test_resolveAdapter_from_partner_array_uses_protocol_type(): void
    {
        $adapter = FederationExternalApiClient::resolveAdapter(['protocol_type' => 'komunitin']);
        $this->assertInstanceOf(KomunitinAdapter::class, $adapter);

        $adapter = FederationExternalApiClient::resolveAdapter(['protocol_type' => 'timeoverflow']);
        $this->assertInstanceOf(TimeOverflowAdapter::class, $adapter);

        $adapter = FederationExternalApiClient::resolveAdapter(['protocol_type' => 'credit_commons']);
        $this->assertInstanceOf(CreditCommonsAdapter::class, $adapter);

        $adapter = FederationExternalApiClient::resolveAdapter(['protocol_type' => 'nexus']);
        $this->assertInstanceOf(NexusAdapter::class, $adapter);
    }

    public function test_resolveAdapter_defaults_to_nexus_when_no_protocol_type(): void
    {
        // Empty array — no protocol_type key at all
        $adapter = FederationExternalApiClient::resolveAdapter([]);
        $this->assertInstanceOf(NexusAdapter::class, $adapter);

        // Array with other keys but no protocol_type
        $adapter = FederationExternalApiClient::resolveAdapter([
            'id' => 1,
            'name' => 'Test Partner',
            'base_url' => 'https://example.com',
        ]);
        $this->assertInstanceOf(NexusAdapter::class, $adapter);
    }
}
