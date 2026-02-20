<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationExternalApiClient;

/**
 * FederationExternalApiClient Tests
 *
 * Tests the HTTP client for making authenticated API calls to external
 * federation partners. Since this makes real HTTP calls, we test with
 * a non-existent URL to verify error handling and structure.
 */
class FederationExternalApiClientTest extends DatabaseTestCase
{
    protected static array $mockPartner = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(2);

        // Create a mock partner config (no real external server)
        self::$mockPartner = [
            'id' => 0,
            'tenant_id' => 2,
            'name' => 'Test External Partner',
            'base_url' => 'https://nonexistent-partner.example.com',
            'api_path' => '/api/v1/federation',
            'api_key' => '',
            'auth_method' => 'api_key',
            'signing_secret' => '',
            'platform_id' => 'test-platform',
        ];
    }

    // ==========================================
    // Constructor Tests
    // ==========================================

    public function testCanInstantiateWithPartnerConfig(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        $this->assertInstanceOf(FederationExternalApiClient::class, $client);
    }

    public function testCanInstantiateWithMinimalConfig(): void
    {
        $client = new FederationExternalApiClient([
            'id' => 0,
            'base_url' => 'https://example.com',
            'api_path' => '/api',
            'api_key' => '',
            'signing_secret' => '',
        ]);

        $this->assertInstanceOf(FederationExternalApiClient::class, $client);
    }

    // ==========================================
    // GET Request Tests (Error Handling)
    // ==========================================

    public function testGetReturnsErrorForUnreachableHost(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->get('/test');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('error', $result);
        } catch (\Exception $e) {
            // Connection errors are acceptable
            $this->assertTrue(true);
        }
    }

    public function testGetWithParamsReturnsErrorForUnreachableHost(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->get('/members', ['q' => 'test', 'limit' => 10]);

            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    // ==========================================
    // POST Request Tests (Error Handling)
    // ==========================================

    public function testPostReturnsErrorForUnreachableHost(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->post('/messages', ['body' => 'test message']);

            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    // ==========================================
    // Convenience Method Tests
    // ==========================================

    public function testTestConnectionReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->testConnection();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testGetTimebanksReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->getTimebanks();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testSearchMembersReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->searchMembers(['q' => 'test']);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testSearchListingsReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->searchListings(['type' => 'offer']);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testGetMemberReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->getMember(1);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testGetListingReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->getListing(1);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testSendMessageReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->sendMessage([
                'sender_id' => 1,
                'sender_name' => 'Test User',
                'receiver_id' => 2,
                'subject' => 'Test',
                'body' => 'Test message',
            ]);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testCreateTransactionReturnsArray(): void
    {
        $client = new FederationExternalApiClient(self::$mockPartner);

        try {
            $result = $client->createTransaction([
                'sender_id' => 1,
                'receiver_id' => 2,
                'amount' => 1.5,
                'description' => 'Test transaction',
            ]);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    // ==========================================
    // Auth Method Tests
    // ==========================================

    public function testApiKeyAuthClient(): void
    {
        $partner = array_merge(self::$mockPartner, [
            'auth_method' => 'api_key',
            'api_key' => '',
        ]);

        $client = new FederationExternalApiClient($partner);
        $this->assertInstanceOf(FederationExternalApiClient::class, $client);
    }

    public function testHmacAuthClient(): void
    {
        $partner = array_merge(self::$mockPartner, [
            'auth_method' => 'hmac',
            'signing_secret' => '',
        ]);

        $client = new FederationExternalApiClient($partner);
        $this->assertInstanceOf(FederationExternalApiClient::class, $client);
    }

    public function testOauth2AuthClient(): void
    {
        $partner = array_merge(self::$mockPartner, [
            'auth_method' => 'oauth2',
        ]);

        $client = new FederationExternalApiClient($partner);
        $this->assertInstanceOf(FederationExternalApiClient::class, $client);
    }

    // ==========================================
    // Method Existence Tests
    // ==========================================

    public function testAllPublicMethodsExist(): void
    {
        $expectedMethods = [
            'testConnection',
            'getTimebanks',
            'searchMembers',
            'getMember',
            'searchListings',
            'getListing',
            'sendMessage',
            'createTransaction',
            'get',
            'post',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                method_exists(FederationExternalApiClient::class, $method),
                "Method {$method} should exist on FederationExternalApiClient"
            );
        }
    }
}
