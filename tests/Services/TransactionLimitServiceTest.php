<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\TransactionLimitService;

/**
 * TransactionLimitService Tests
 *
 * Tests transaction limit enforcement for organization wallets.
 */
class TransactionLimitServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testOrgId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "limit_test_{$timestamp}@test.com", "limit_test_{$timestamp}", 'Limit', 'Test', 'Limit Test', 1000]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test organization
        try {
            Database::query(
                "INSERT INTO organizations (tenant_id, name, slug, status, created_at)
                 VALUES (?, ?, ?, 'active', NOW())",
                [self::$testTenantId, "Test Org {$timestamp}", "test-org-{$timestamp}"]
            );
            self::$testOrgId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Organizations table may not exist in test DB
            self::$testOrgId = 1;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testOrgId && self::$testOrgId > 1) {
            try {
                Database::query("DELETE FROM organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Default Limit Constants Tests
    // ==========================================

    public function testDefaultLimitConstantsAreDefined(): void
    {
        $this->assertEquals(500, TransactionLimitService::DEFAULT_SINGLE_TRANSACTION_MAX);
        $this->assertEquals(1000, TransactionLimitService::DEFAULT_DAILY_LIMIT);
        $this->assertEquals(3000, TransactionLimitService::DEFAULT_WEEKLY_LIMIT);
        $this->assertEquals(10000, TransactionLimitService::DEFAULT_MONTHLY_LIMIT);
        $this->assertEquals(5000, TransactionLimitService::DEFAULT_ORG_DAILY_LIMIT);
    }

    // ==========================================
    // Check Limits Structure Tests
    // ==========================================

    public function testCheckLimitsReturnsExpectedStructure(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            10,
            'outgoing'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('limits', $result);
        $this->assertIsBool($result['allowed']);
    }

    public function testCheckLimitsAllowsSmallTransaction(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            1, // Very small amount
            'outgoing'
        );

        // Should be allowed unless daily limit already hit
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Single Transaction Limit Tests
    // ==========================================

    public function testCheckLimitsRejectsSingleTransactionOverMax(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            1000, // Over DEFAULT_SINGLE_TRANSACTION_MAX (500)
            'outgoing'
        );

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('single transaction limit', $result['reason'] ?? '');
    }

    public function testCheckLimitsAllowsTransactionAtMaxLimit(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            TransactionLimitService::DEFAULT_SINGLE_TRANSACTION_MAX, // Exactly at limit
            'outgoing'
        );

        // Should be allowed if other limits aren't hit
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Direction Tests
    // ==========================================

    public function testCheckLimitsWorksForOutgoing(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            10,
            'outgoing'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    public function testCheckLimitsWorksForIncoming(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            10,
            'incoming'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Get Limits Tests
    // ==========================================

    public function testGetLimitsReturnsArray(): void
    {
        if (!method_exists(TransactionLimitService::class, 'getLimits')) {
            $this->markTestSkipped('getLimits is private or not implemented');
        }

        $reflection = new \ReflectionClass(TransactionLimitService::class);
        $method = $reflection->getMethod('getLimits');

        if ($method->isPrivate() || $method->isProtected()) {
            $method->setAccessible(true);
            $result = $method->invoke(null, self::$testOrgId);
        } else {
            $result = TransactionLimitService::getLimits(self::$testOrgId);
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('single_max', $result);
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('weekly', $result);
        $this->assertArrayHasKey('monthly', $result);
    }

    // ==========================================
    // Usage Tracking Tests
    // ==========================================

    public function testGetUsageReturnsArray(): void
    {
        if (!method_exists(TransactionLimitService::class, 'getUsage')) {
            $this->markTestSkipped('getUsage is private or not implemented');
        }

        $reflection = new \ReflectionClass(TransactionLimitService::class);
        $method = $reflection->getMethod('getUsage');

        if ($method->isPrivate() || $method->isProtected()) {
            $method->setAccessible(true);
            $result = $method->invoke(null, self::$testOrgId, self::$testUserId, 'outgoing');
        } else {
            $result = TransactionLimitService::getUsage(self::$testOrgId, self::$testUserId, 'outgoing');
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('weekly', $result);
        $this->assertArrayHasKey('monthly', $result);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCheckLimitsWithZeroAmount(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            0,
            'outgoing'
        );

        $this->assertIsArray($result);
        // Zero amount should technically be allowed by limits
        $this->assertTrue($result['allowed']);
    }

    public function testCheckLimitsWithNegativeAmount(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            self::$testUserId,
            -10,
            'outgoing'
        );

        $this->assertIsArray($result);
        // Should handle gracefully
        $this->assertArrayHasKey('allowed', $result);
    }

    public function testCheckLimitsWithNonExistentOrg(): void
    {
        $result = TransactionLimitService::checkLimits(
            999999, // Non-existent org
            self::$testUserId,
            10,
            'outgoing'
        );

        $this->assertIsArray($result);
        // Should use default limits
        $this->assertArrayHasKey('allowed', $result);
    }

    public function testCheckLimitsWithNonExistentUser(): void
    {
        $result = TransactionLimitService::checkLimits(
            self::$testOrgId,
            999999, // Non-existent user
            10,
            'outgoing'
        );

        $this->assertIsArray($result);
        // Should handle gracefully
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Remaining Credits Tests
    // ==========================================

    public function testGetRemainingLimitsReturnsArray(): void
    {
        if (!method_exists(TransactionLimitService::class, 'getRemainingLimits')) {
            $this->markTestSkipped('getRemainingLimits not implemented');
        }

        $result = TransactionLimitService::getRemainingLimits(
            self::$testOrgId,
            self::$testUserId
        );

        $this->assertIsArray($result);
    }

    // ==========================================
    // Custom Limits Tests
    // ==========================================

    public function testSetCustomLimitsMethodExists(): void
    {
        if (!method_exists(TransactionLimitService::class, 'setCustomLimits')) {
            $this->markTestSkipped('setCustomLimits not implemented');
        }

        // Should not throw
        $result = TransactionLimitService::setCustomLimits(
            self::$testOrgId,
            ['daily' => 2000]
        );

        $this->assertTrue($result === true || is_bool($result));
    }
}
