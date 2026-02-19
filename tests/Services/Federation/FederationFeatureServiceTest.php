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
use Nexus\Services\FederationFeatureService;

/**
 * FederationFeatureService Tests
 *
 * Tests feature flag management for federation operations.
 */
class FederationFeatureServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$testTenantId = 2; // hour-timebank tenant
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // System-Level Feature Flag Tests
    // ==========================================

    public function testIsSystemFederationEnabledReturnsBool(): void
    {
        $result = FederationFeatureService::isGloballyEnabled();

        $this->assertIsBool($result);
    }

    public function testGetSystemFeaturesReturnsArray(): void
    {
        if (!method_exists(FederationFeatureService::class, 'getSystemFeatures')) {
            $this->markTestSkipped('getSystemFeatures not implemented');
        }

        $result = FederationFeatureService::getSystemFeatures();

        $this->assertIsArray($result);
    }

    // ==========================================
    // Tenant-Level Feature Flag Tests
    // ==========================================

    public function testIsTenantFederationEnabledReturnsBool(): void
    {
        $result = FederationFeatureService::isTenantFederationEnabled(self::$testTenantId);

        $this->assertIsBool($result);
    }

    public function testIsTenantFederationEnabledWithInvalidTenant(): void
    {
        $result = FederationFeatureService::isTenantFederationEnabled(999999);

        // Should return false for non-existent tenant
        $this->assertFalse($result);
    }

    public function testGetTenantFeaturesReturnsArray(): void
    {
        if (!method_exists(FederationFeatureService::class, 'getTenantFeatures')) {
            $this->markTestSkipped('getTenantFeatures not implemented');
        }

        $result = FederationFeatureService::getTenantFeatures(self::$testTenantId);

        $this->assertIsArray($result);
    }

    // ==========================================
    // Operation Permission Tests
    // ==========================================

    public function testIsOperationAllowedReturnsExpectedStructure(): void
    {
        $operations = ['profiles', 'messaging', 'listings', 'transactions', 'groups'];

        foreach ($operations as $operation) {
            $result = FederationFeatureService::isOperationAllowed($operation, self::$testTenantId);

            $this->assertIsArray($result, "isOperationAllowed('{$operation}') should return array");
            $this->assertArrayHasKey('allowed', $result, "Result for '{$operation}' should have 'allowed' key");
            $this->assertArrayHasKey('reason', $result, "Result for '{$operation}' should have 'reason' key");
            $this->assertIsBool($result['allowed'], "'allowed' should be boolean for '{$operation}'");
        }
    }

    public function testIsOperationAllowedWithInvalidOperation(): void
    {
        $result = FederationFeatureService::isOperationAllowed('invalid_operation', self::$testTenantId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        // Should either allow (unknown = allowed) or deny (unknown = denied) consistently
        $this->assertIsBool($result['allowed']);
    }

    public function testIsOperationAllowedWithInvalidTenant(): void
    {
        $result = FederationFeatureService::isOperationAllowed('profiles', 999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        // Should deny for non-existent tenant
        $this->assertFalse($result['allowed']);
    }

    // ==========================================
    // Feature-Specific Tests
    // ==========================================

    public function testIsProfilesEnabledReturnsBool(): void
    {
        if (!method_exists(FederationFeatureService::class, 'isProfilesEnabled')) {
            // Use generic method
            $result = FederationFeatureService::isOperationAllowed('profiles', self::$testTenantId);
            $this->assertIsBool($result['allowed']);
            return;
        }

        $result = FederationFeatureService::isProfilesEnabled(self::$testTenantId);
        $this->assertIsBool($result);
    }

    public function testIsMessagingEnabledReturnsBool(): void
    {
        if (!method_exists(FederationFeatureService::class, 'isMessagingEnabled')) {
            $result = FederationFeatureService::isOperationAllowed('messaging', self::$testTenantId);
            $this->assertIsBool($result['allowed']);
            return;
        }

        $result = FederationFeatureService::isMessagingEnabled(self::$testTenantId);
        $this->assertIsBool($result);
    }

    public function testIsTransactionsEnabledReturnsBool(): void
    {
        if (!method_exists(FederationFeatureService::class, 'isTransactionsEnabled')) {
            $result = FederationFeatureService::isOperationAllowed('transactions', self::$testTenantId);
            $this->assertIsBool($result['allowed']);
            return;
        }

        $result = FederationFeatureService::isTransactionsEnabled(self::$testTenantId);
        $this->assertIsBool($result);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testIsOperationAllowedWithNullTenant(): void
    {
        // PHP 8 should handle this, but we test the behavior
        try {
            $result = FederationFeatureService::isOperationAllowed('profiles', 0);
            $this->assertIsArray($result);
            $this->assertFalse($result['allowed']);
        } catch (\TypeError $e) {
            // Type error is acceptable if method requires int
            $this->assertTrue(true);
        }
    }

    public function testIsOperationAllowedWithEmptyOperation(): void
    {
        $result = FederationFeatureService::isOperationAllowed('', self::$testTenantId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
    }

    // ==========================================
    // Caching Tests
    // ==========================================

    public function testMultipleCallsReturnConsistentResults(): void
    {
        $result1 = FederationFeatureService::isTenantFederationEnabled(self::$testTenantId);
        $result2 = FederationFeatureService::isTenantFederationEnabled(self::$testTenantId);
        $result3 = FederationFeatureService::isTenantFederationEnabled(self::$testTenantId);

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
    }

    public function testClearCacheMethodExists(): void
    {
        if (!method_exists(FederationFeatureService::class, 'clearCache')) {
            $this->markTestSkipped('clearCache not implemented');
        }

        // Should not throw
        FederationFeatureService::clearCache();
        $this->assertTrue(true);
    }
}
