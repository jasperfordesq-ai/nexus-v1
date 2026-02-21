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
use Nexus\Services\FederationGateway;
use Nexus\Services\FederationFeatureService;

/**
 * FederationGateway Tests
 *
 * Tests the main federation gateway that controls all cross-tenant operations.
 */
class FederationGatewayTest extends DatabaseTestCase
{
    protected static ?int $tenant1Id = null;
    protected static ?int $tenant2Id = null;
    protected static ?int $user1Id = null;
    protected static ?int $user2Id = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Use existing test tenants
        self::$tenant1Id = 1; // Master tenant
        self::$tenant2Id = 2; // hour-timebank

        // Create test users in each tenant
        self::createTestUsers();
    }

    protected static function createTestUsers(): void
    {
        $timestamp = time();

        // User in tenant 1
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant1Id, "fed_test_user1_{$timestamp}@test.com", "fed_test_user1_{$timestamp}", 'Fed', 'User1', 'Fed User1', 100]
        );
        self::$user1Id = (int)Database::getInstance()->lastInsertId();

        // User in tenant 2
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant2Id, "fed_test_user2_{$timestamp}@test.com", "fed_test_user2_{$timestamp}", 'Fed', 'User2', 'Fed User2', 100]
        );
        self::$user2Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test users
        if (self::$user1Id) {
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$user1Id]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$user1Id]);
            } catch (\Exception $e) {}
        }
        if (self::$user2Id) {
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$user2Id]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$user2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Same Tenant Tests (No Federation Needed)
    // ==========================================

    public function testCanViewProfileSameTenantAlwaysAllowed(): void
    {
        $result = FederationGateway::canViewProfile(
            self::$tenant1Id,
            self::$tenant1Id,
            self::$user1Id,
            self::$user1Id
        );

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    public function testCanSendMessageSameTenantAlwaysAllowed(): void
    {
        $result = FederationGateway::canSendMessage(
            self::$user1Id,
            self::$tenant1Id,
            self::$user1Id, // Same user (edge case)
            self::$tenant1Id
        );

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    // ==========================================
    // Cross-Tenant Profile View Tests
    // ==========================================

    public function testCanViewProfileCrossTenantReturnsArrayStructure(): void
    {
        $result = FederationGateway::canViewProfile(
            self::$tenant1Id,
            self::$tenant2Id,
            self::$user2Id,
            self::$user1Id
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertIsBool($result['allowed']);
    }

    public function testCanViewProfileCrossTenantWithNullViewer(): void
    {
        $result = FederationGateway::canViewProfile(
            self::$tenant1Id,
            self::$tenant2Id,
            self::$user2Id,
            null // Anonymous viewer
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Cross-Tenant Messaging Tests
    // ==========================================

    public function testCanSendMessageCrossTenantReturnsArrayStructure(): void
    {
        $result = FederationGateway::canSendMessage(
            self::$user1Id,
            self::$tenant1Id,
            self::$user2Id,
            self::$tenant2Id
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
    }

    // ==========================================
    // Transaction Permission Tests
    // ==========================================

    public function testCanPerformTransactionSameTenantAllowed(): void
    {
        // Check if method exists (may not be implemented yet)
        if (!method_exists(FederationGateway::class, 'canPerformTransaction')) {
            $this->markTestSkipped('canPerformTransaction not implemented');
        }

        $result = FederationGateway::canPerformTransaction(
            self::$user1Id,
            self::$tenant1Id,
            self::$user1Id,
            self::$tenant1Id
        );

        $this->assertTrue($result['allowed']);
    }

    public function testCanPerformTransactionCrossTenantReturnsArrayStructure(): void
    {
        if (!method_exists(FederationGateway::class, 'canPerformTransaction')) {
            $this->markTestSkipped('canPerformTransaction not implemented');
        }

        $result = FederationGateway::canPerformTransaction(
            self::$user1Id,
            self::$tenant1Id,
            self::$user2Id,
            self::$tenant2Id
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
    }

    // ==========================================
    // Listing Access Tests
    // ==========================================

    public function testCanViewListingSameTenantAllowed(): void
    {
        if (!method_exists(FederationGateway::class, 'canViewListing')) {
            $this->markTestSkipped('canViewListing not implemented');
        }

        $result = FederationGateway::canViewListing(
            self::$tenant1Id,
            self::$tenant1Id,
            1, // Dummy listing ID
            self::$user1Id
        );

        $this->assertTrue($result['allowed']);
    }

    // ==========================================
    // Group Access Tests
    // ==========================================

    public function testCanViewGroupSameTenantAllowed(): void
    {
        if (!method_exists(FederationGateway::class, 'canViewGroup')) {
            $this->markTestSkipped('canViewGroup not implemented');
        }

        $result = FederationGateway::canViewGroup(
            self::$tenant1Id,
            self::$tenant1Id,
            1, // Dummy group ID
            self::$user1Id
        );

        $this->assertTrue($result['allowed']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCanViewProfileWithInvalidTenantId(): void
    {
        $result = FederationGateway::canViewProfile(
            999999, // Non-existent tenant
            self::$tenant1Id,
            self::$user1Id,
            self::$user1Id
        );

        // Should return structured response even for invalid input
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    public function testCanViewProfileWithInvalidUserId(): void
    {
        $result = FederationGateway::canViewProfile(
            self::$tenant1Id,
            self::$tenant2Id,
            999999, // Non-existent user
            self::$user1Id
        );

        // Should handle gracefully
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Response Consistency Tests
    // ==========================================

    public function testAllGatewayMethodsReturnConsistentStructure(): void
    {
        $methods = [
            'canViewProfile' => [self::$tenant1Id, self::$tenant2Id, self::$user2Id, self::$user1Id],
            'canSendMessage' => [self::$user1Id, self::$tenant1Id, self::$user2Id, self::$tenant2Id],
        ];

        foreach ($methods as $method => $args) {
            if (!method_exists(FederationGateway::class, $method)) {
                continue;
            }

            $result = call_user_func_array([FederationGateway::class, $method], $args);

            $this->assertIsArray($result, "{$method} should return array");
            $this->assertArrayHasKey('allowed', $result, "{$method} should have 'allowed' key");
            $this->assertArrayHasKey('reason', $result, "{$method} should have 'reason' key");
            $this->assertIsBool($result['allowed'], "{$method} 'allowed' should be boolean");
        }
    }
}
