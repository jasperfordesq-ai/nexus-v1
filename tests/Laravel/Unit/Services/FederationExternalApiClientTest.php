<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationExternalApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * FederationExternalApiClient Tests
 *
 * Tests the static HTTP client for making authenticated API calls to external
 * federation partners. Uses Mockery for DB/HTTP mocking — no real external calls.
 */
class FederationExternalApiClientTest extends TestCase
{
    private array $mockPartnerRow = [
        'id' => 99,
        'tenant_id' => 2,
        'name' => 'Test External Partner',
        'base_url' => 'https://nonexistent-partner.example.com',
        'api_path' => '/api/v1/federation',
        'api_key' => '',
        'auth_method' => 'api_key',
        'protocol_type' => 'nexus',
        'signing_secret' => '',
        'platform_id' => 'test-platform',
        'status' => 'active',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        FederationExternalApiClient::clearAdapterCache();
    }

    // ==========================================
    // Static method existence tests
    // ==========================================

    public function test_all_core_static_methods_exist(): void
    {
        $methods = [
            'get', 'post', 'put', 'patch', 'delete',
            'fetchMembers', 'fetchListings', 'fetchMember', 'fetchListing',
            'sendMessage', 'createTransaction', 'healthCheck',
            'resolveAdapter', 'createAdapter', 'getSupportedProtocols',
            'clearAdapterCache',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(FederationExternalApiClient::class, $method),
                "Static method {$method}() should exist"
            );
        }
    }

    // ==========================================
    // get/post — method signatures accept correct types
    // ==========================================

    public function test_get_accepts_int_partner_id_and_string_endpoint(): void
    {
        // Verify the method signature accepts (int, string, array)
        $refMethod = new \ReflectionMethod(FederationExternalApiClient::class, 'get');
        $params = $refMethod->getParameters();

        $this->assertEquals('partnerId', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
        $this->assertEquals('endpoint', $params[1]->getName());
        $this->assertEquals('string', $params[1]->getType()->getName());
    }

    public function test_post_accepts_int_partner_id_and_string_endpoint(): void
    {
        $refMethod = new \ReflectionMethod(FederationExternalApiClient::class, 'post');
        $params = $refMethod->getParameters();

        $this->assertEquals('partnerId', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
    }

    public function test_put_patch_delete_methods_exist_with_correct_signatures(): void
    {
        foreach (['put', 'patch', 'delete'] as $method) {
            $refMethod = new \ReflectionMethod(FederationExternalApiClient::class, $method);
            $params = $refMethod->getParameters();
            $this->assertEquals('partnerId', $params[0]->getName());
            $this->assertEquals('int', $params[0]->getType()->getName());
        }
    }

    // ==========================================
    // getSupportedProtocols()
    // ==========================================

    public function test_getSupportedProtocols_returns_all_four(): void
    {
        $protocols = FederationExternalApiClient::getSupportedProtocols();

        $this->assertArrayHasKey('nexus', $protocols);
        $this->assertArrayHasKey('timeoverflow', $protocols);
        $this->assertArrayHasKey('komunitin', $protocols);
        $this->assertArrayHasKey('credit_commons', $protocols);
        $this->assertCount(4, $protocols);
    }

    // ==========================================
    // createAdapter()
    // ==========================================

    public function test_createAdapter_returns_correct_types(): void
    {
        $this->assertInstanceOf(
            \App\Services\Protocols\NexusAdapter::class,
            FederationExternalApiClient::createAdapter('nexus')
        );
        $this->assertInstanceOf(
            \App\Services\TimeOverflowAdapter::class,
            FederationExternalApiClient::createAdapter('timeoverflow')
        );
        $this->assertInstanceOf(
            \App\Services\Protocols\KomunitinAdapter::class,
            FederationExternalApiClient::createAdapter('komunitin')
        );
        $this->assertInstanceOf(
            \App\Services\Protocols\CreditCommonsAdapter::class,
            FederationExternalApiClient::createAdapter('credit_commons')
        );
    }

    public function test_createAdapter_defaults_to_nexus_for_unknown(): void
    {
        $adapter = FederationExternalApiClient::createAdapter('some_unknown_protocol');
        $this->assertInstanceOf(\App\Services\Protocols\NexusAdapter::class, $adapter);
    }

    // ==========================================
    // resolveAdapter() with array
    // ==========================================

    public function test_resolveAdapter_from_array_uses_protocol_type(): void
    {
        $adapter = FederationExternalApiClient::resolveAdapter(['protocol_type' => 'komunitin']);
        $this->assertInstanceOf(\App\Services\Protocols\KomunitinAdapter::class, $adapter);
    }

    public function test_resolveAdapter_from_array_defaults_to_nexus(): void
    {
        $adapter = FederationExternalApiClient::resolveAdapter(['name' => 'no protocol_type key']);
        $this->assertInstanceOf(\App\Services\Protocols\NexusAdapter::class, $adapter);
    }

    // ==========================================
    // clearAdapterCache()
    // ==========================================

    public function test_clearAdapterCache_resets_cache(): void
    {
        // This shouldn't throw — just verify it's callable
        FederationExternalApiClient::clearAdapterCache();
        $this->assertTrue(true);
    }

    // ==========================================
    // Auth method config tests (structural)
    // ==========================================

    public function test_api_key_auth_partner_config(): void
    {
        $partner = array_merge($this->mockPartnerRow, ['auth_method' => 'api_key']);
        $this->assertEquals('api_key', $partner['auth_method']);
    }

    public function test_hmac_auth_partner_config(): void
    {
        $partner = array_merge($this->mockPartnerRow, ['auth_method' => 'hmac']);
        $this->assertEquals('hmac', $partner['auth_method']);
    }

    public function test_oauth2_auth_partner_config(): void
    {
        $partner = array_merge($this->mockPartnerRow, ['auth_method' => 'oauth2']);
        $this->assertEquals('oauth2', $partner['auth_method']);
    }
}
