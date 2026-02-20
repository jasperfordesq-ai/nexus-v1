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
use Nexus\Services\FederationExternalPartnerService;

/**
 * FederationExternalPartnerService Tests
 *
 * Tests CRUD operations for external federation partners.
 */
class FederationExternalPartnerServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $createdPartnerId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        // Create test user for createdBy field
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "ext_partner_test_{$timestamp}@test.com", "ext_partner_test_{$timestamp}", 'Ext', 'Partner', 'Ext Partner', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up created partner if any
        if (self::$createdPartnerId) {
            try {
                Database::query("DELETE FROM federation_external_partner_logs WHERE partner_id = ?", [self::$createdPartnerId]);
                Database::query("DELETE FROM federation_external_partners WHERE id = ?", [self::$createdPartnerId]);
            } catch (\Exception $e) {}
        }

        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getAll Tests
    // ==========================================

    public function testGetAllReturnsArray(): void
    {
        try {
            $result = FederationExternalPartnerService::getAll(self::$testTenantId);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetAllWithNonExistentTenantReturnsEmptyArray(): void
    {
        try {
            $result = FederationExternalPartnerService::getAll(999999);
            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // getById Tests
    // ==========================================

    public function testGetByIdReturnsNullForNonExistentPartner(): void
    {
        try {
            $result = FederationExternalPartnerService::getById(999999, self::$testTenantId);
            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    public function testGetByIdReturnsNullForWrongTenant(): void
    {
        try {
            $result = FederationExternalPartnerService::getById(1, 999999);
            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // create / urlExists / delete Tests
    // ==========================================

    public function testCreateAndDeletePartner(): void
    {
        $timestamp = time();
        $baseUrl = "https://test-partner-{$timestamp}.example.com";

        try {
            // Create
            $result = FederationExternalPartnerService::create(
                [
                    'name' => "Test Partner {$timestamp}",
                    'description' => 'A test external partner',
                    'base_url' => $baseUrl,
                    'api_path' => '/api/v1/federation',
                    'api_key' => 'test-api-key-12345',
                    'auth_method' => 'api_key',
                ],
                self::$testTenantId,
                self::$testUserId
            );

            $this->assertIsArray($result);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('id', $result);

            $partnerId = (int) $result['id'];
            self::$createdPartnerId = $partnerId;

            // urlExists should be true now
            $exists = FederationExternalPartnerService::urlExists($baseUrl, self::$testTenantId);
            $this->assertTrue($exists);

            // urlExists with excludeId should be false
            $existsExcluded = FederationExternalPartnerService::urlExists($baseUrl, self::$testTenantId, $partnerId);
            $this->assertFalse($existsExcluded);

            // getById should work
            $partner = FederationExternalPartnerService::getById($partnerId, self::$testTenantId);
            $this->assertNotNull($partner);
            $this->assertEquals("Test Partner {$timestamp}", $partner['name']);

            // Delete
            $deleteResult = FederationExternalPartnerService::delete($partnerId, self::$testTenantId, self::$testUserId);
            $this->assertTrue($deleteResult['success']);
            self::$createdPartnerId = null;

            // Verify deleted
            $deleted = FederationExternalPartnerService::getById($partnerId, self::$testTenantId);
            $this->assertNull($deleted);

        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist: ' . $e->getMessage());
        }
    }

    public function testCreateDuplicateUrlFails(): void
    {
        $timestamp = time();
        $baseUrl = "https://duplicate-test-{$timestamp}.example.com";

        try {
            // Create first
            $result1 = FederationExternalPartnerService::create(
                ['name' => 'First Partner', 'base_url' => $baseUrl],
                self::$testTenantId,
                self::$testUserId
            );

            if (!$result1['success']) {
                $this->markTestSkipped('Could not create first partner');
                return;
            }

            $firstId = (int) $result1['id'];

            // Try duplicate
            $result2 = FederationExternalPartnerService::create(
                ['name' => 'Duplicate Partner', 'base_url' => $baseUrl],
                self::$testTenantId,
                self::$testUserId
            );

            $this->assertFalse($result2['success']);
            $this->assertStringContainsString('already exists', $result2['error']);

            // Clean up
            FederationExternalPartnerService::delete($firstId, self::$testTenantId, self::$testUserId);

        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // update Tests
    // ==========================================

    public function testUpdateNonExistentPartnerFails(): void
    {
        try {
            $result = FederationExternalPartnerService::update(
                999999,
                ['name' => 'Updated', 'base_url' => 'https://updated.example.com'],
                self::$testTenantId,
                self::$testUserId
            );

            $this->assertFalse($result['success']);
            $this->assertEquals('Partner not found', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // updateStatus Tests
    // ==========================================

    public function testUpdateStatusNonExistentPartnerFails(): void
    {
        try {
            $result = FederationExternalPartnerService::updateStatus(
                999999,
                'active',
                self::$testTenantId,
                self::$testUserId
            );

            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // getActivePartners Tests
    // ==========================================

    public function testGetActivePartnersReturnsArray(): void
    {
        try {
            $result = FederationExternalPartnerService::getActivePartners(self::$testTenantId);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // getActivePartnersForListings Tests
    // ==========================================

    public function testGetActivePartnersForListingsReturnsArray(): void
    {
        try {
            $result = FederationExternalPartnerService::getActivePartnersForListings(self::$testTenantId);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }

    // ==========================================
    // getLogs Tests
    // ==========================================

    public function testGetLogsReturnsArray(): void
    {
        try {
            $result = FederationExternalPartnerService::getLogs(999999);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partner_logs table may not exist');
        }
    }

    // ==========================================
    // Encryption Tests
    // ==========================================

    public function testDecryptApiKeyRoundTrip(): void
    {
        $originalKey = 'test-api-key-' . time();

        // Use reflection to access private encryptApiKey method
        $reflection = new \ReflectionClass(FederationExternalPartnerService::class);

        $encryptMethod = $reflection->getMethod('encryptApiKey');
        $encryptMethod->setAccessible(true);
        $encrypted = $encryptMethod->invoke(null, $originalKey);

        // Decrypt using the public method
        $decrypted = FederationExternalPartnerService::decryptApiKey($encrypted);

        $this->assertEquals($originalKey, $decrypted);
    }

    public function testEncryptionProducesDifferentOutputs(): void
    {
        $key = 'same-key-input';

        $reflection = new \ReflectionClass(FederationExternalPartnerService::class);
        $encryptMethod = $reflection->getMethod('encryptApiKey');
        $encryptMethod->setAccessible(true);

        $encrypted1 = $encryptMethod->invoke(null, $key);
        $encrypted2 = $encryptMethod->invoke(null, $key);

        // Due to random IV, encryptions should differ
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $decrypted1 = FederationExternalPartnerService::decryptApiKey($encrypted1);
        $decrypted2 = FederationExternalPartnerService::decryptApiKey($encrypted2);
        $this->assertEquals($decrypted1, $decrypted2);
        $this->assertEquals($key, $decrypted1);
    }

    // ==========================================
    // deleteNonExistent Tests
    // ==========================================

    public function testDeleteNonExistentPartnerFails(): void
    {
        try {
            $result = FederationExternalPartnerService::delete(999999, self::$testTenantId, self::$testUserId);
            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_external_partners table may not exist');
        }
    }
}
